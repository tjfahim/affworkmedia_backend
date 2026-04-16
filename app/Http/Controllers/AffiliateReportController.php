<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AffiliateClick;
use App\Models\AffiliateSale;
use App\Models\GameManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; 

class AffiliateReportController extends Controller
{
    /**
     * GET /admin/affiliate-reports
     * Returns summary for ALL affiliates with nested offer & sub breakdowns.
     */
    public function index(Request $request)
    {
        $dateFrom   = $request->query('date_from');
        $dateTo     = $request->query('date_to');
        $filterAff  = $request->query('affiliate_id');
        $filterGame = $request->query('game_id');
        $filterCountry = $request->query('country');
        $perPage    = (int) $request->query('per_page', 20);

        // ── 1. Build base click query ────────────────────────────────────────
        $clickBase = AffiliateClick::query()
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
            ->when($filterCountry, fn($q) => $q->where('country', $filterCountry));

        // ── 2. Build base sale queries ────────────────────────────────────────
        $visibleSaleBase = AffiliateSale::query()
            ->where('is_hidden', false)
            ->when($dateFrom, fn($q) => $q->whereDate('purchased_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('purchased_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame));

        $hiddenSaleBase = AffiliateSale::query()
            ->where('is_hidden', true)
            ->when($dateFrom, fn($q) => $q->whereDate('purchased_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('purchased_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame));

        $allSaleBase = AffiliateSale::query()
            ->when($dateFrom, fn($q) => $q->whereDate('purchased_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('purchased_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame));

        // ── 3. Aggregate clicks per affiliate ────────────────────────────────
        $clicksByAffiliate = (clone $clickBase)
            ->select('affiliate_id',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(is_unique) as unique_clicks'),
                DB::raw('COUNT(DISTINCT country) as countries_count')
            )
            ->groupBy('affiliate_id')
            ->get()
            ->keyBy('affiliate_id');

        // ── 4. Aggregate sales per affiliate ─────────────────────────────────
        $visibleSalesByAffiliate = (clone $visibleSaleBase)
            ->select('affiliate_id',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(commission_amount) as total_commission'),
                DB::raw('SUM(package_price) as total_revenue')
            )
            ->groupBy('affiliate_id')
            ->get()
            ->keyBy('affiliate_id');

        $hiddenSalesByAffiliate = (clone $hiddenSaleBase)
            ->select('affiliate_id',
                DB::raw('COUNT(*) as hidden_sales'),
                DB::raw('SUM(package_price) as hidden_revenue')
            )
            ->groupBy('affiliate_id')
            ->get()
            ->keyBy('affiliate_id');

        // ── 5. Get distinct affiliate IDs and load users ──────────────────────
        $affiliateIds = $clicksByAffiliate->keys()
            ->merge($visibleSalesByAffiliate->keys())
            ->merge($hiddenSalesByAffiliate->keys())
            ->unique();

        $usersQuery = User::whereIn('id', $affiliateIds)
            ->when($filterAff, fn($q) => $q->where('id', $filterAff));

        $affiliates = $usersQuery->orderBy('id')->paginate($perPage);
        $affiliateIds_page = $affiliates->pluck('id');

        // ── 6. Offer-level breakdown ──────────────────────────────────────────
        $clicksByOffer = (clone $clickBase)
            ->whereIn('affiliate_id', $affiliateIds_page)
            ->select('affiliate_id', 'game_id',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(is_unique) as unique_clicks'),
                DB::raw('COUNT(DISTINCT country) as countries_count'),
                DB::raw('GROUP_CONCAT(DISTINCT country ORDER BY country SEPARATOR ",") as countries')
            )
            ->groupBy('affiliate_id', 'game_id')
            ->get();

        $visibleSalesByOffer = (clone $visibleSaleBase)
            ->whereIn('affiliate_id', $affiliateIds_page)
            ->select('affiliate_id', 'game_id',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(commission_amount) as total_commission'),
                DB::raw('SUM(package_price) as total_revenue'),
                DB::raw('GROUP_CONCAT(DISTINCT package_type ORDER BY package_type SEPARATOR ",") as package_types')
            )
            ->groupBy('affiliate_id', 'game_id')
            ->get();

        $hiddenSalesByOffer = (clone $hiddenSaleBase)
            ->whereIn('affiliate_id', $affiliateIds_page)
            ->select('affiliate_id', 'game_id',
                DB::raw('COUNT(*) as hidden_sales'),
                DB::raw('SUM(package_price) as hidden_revenue')
            )
            ->groupBy('affiliate_id', 'game_id')
            ->get();

        // ── 7. Sub-account breakdown (Fixed: fresh query to avoid ambiguity) ───
        $clicksBySub = (clone $clickBase)
            ->whereIn('affiliate_id', $affiliateIds_page)
            ->select('affiliate_id', 'game_id',
                'sub1', 'sub2', 'sub3', 'sub4', 'sub5', 'sub6',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(is_unique) as unique_clicks'),
                DB::raw('COUNT(DISTINCT country) as countries_count'),
                DB::raw('GROUP_CONCAT(DISTINCT country ORDER BY country SEPARATOR ",") as countries'),
                DB::raw('COUNT(DISTINCT device_type) as device_types_count'),
                DB::raw('GROUP_CONCAT(DISTINCT device_type ORDER BY device_type SEPARATOR ",") as device_types'),
                DB::raw('GROUP_CONCAT(DISTINCT browser ORDER BY browser SEPARATOR ",") as browsers')
            )
            ->groupBy('affiliate_id', 'game_id', 'sub1', 'sub2', 'sub3', 'sub4', 'sub5', 'sub6')
            ->get();

        // Fresh query for visibleSalesBySub - no cloning to avoid ambiguous column
        $visibleSalesBySub = AffiliateSale::query()
            ->where('is_hidden', false)
            ->when($dateFrom, fn($q) => $q->whereDate('purchased_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('purchased_at', '<=', $dateTo))
            ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('affiliate_sales.game_id', $filterGame))
            ->join('affiliate_clicks', 'affiliate_sales.click_id', '=', 'affiliate_clicks.id')
            ->whereIn('affiliate_sales.affiliate_id', $affiliateIds_page)
            ->select(
                'affiliate_sales.affiliate_id',
                'affiliate_sales.game_id',
                'affiliate_clicks.sub1',
                'affiliate_clicks.sub2',
                'affiliate_clicks.sub3',
                'affiliate_clicks.sub4',
                'affiliate_clicks.sub5',
                'affiliate_clicks.sub6',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(affiliate_sales.commission_amount) as total_commission'),
                DB::raw('SUM(affiliate_sales.package_price) as total_revenue'),
                DB::raw('GROUP_CONCAT(DISTINCT affiliate_sales.package_type ORDER BY affiliate_sales.package_type SEPARATOR ",") as package_types')
            )
            ->groupBy(
                'affiliate_sales.affiliate_id', 'affiliate_sales.game_id',
                'affiliate_clicks.sub1', 'affiliate_clicks.sub2',
                'affiliate_clicks.sub3', 'affiliate_clicks.sub4',
                'affiliate_clicks.sub5', 'affiliate_clicks.sub6'
            )
            ->get();

        // ── 8. Load games map ─────────────────────────────────────────────────
        $gameIds = $clicksByOffer->pluck('game_id')
            ->merge($visibleSalesByOffer->pluck('game_id'))
            ->merge($hiddenSalesByOffer->pluck('game_id'))
            ->unique()->filter();
        $games = GameManage::whereIn('id', $gameIds)->get()->keyBy('id');

        // ── 9. Assemble response ─────────────────────────────────────────────
        $result = $affiliates->map(function ($user) use (
            $clicksByAffiliate, $visibleSalesByAffiliate, $hiddenSalesByAffiliate,
            $clicksByOffer, $visibleSalesByOffer, $hiddenSalesByOffer,
            $clicksBySub, $visibleSalesBySub, $games
        ) {
            $uid = $user->id;
            $uc = $clicksByAffiliate->get($uid);
            $uvs = $visibleSalesByAffiliate->get($uid);
            $uhs = $hiddenSalesByAffiliate->get($uid);

            $visibleSalesCount = $uvs ? (int)$uvs->total_sales : 0;
            $visibleSalesRevenue = $uvs ? (float)$uvs->total_revenue : 0;
            $visibleSalesCommission = $uvs ? (float)$uvs->total_commission : 0;
            $hiddenSalesCount = $uhs ? (int)$uhs->hidden_sales : 0;
            $hiddenSalesRevenue = $uhs ? (float)$uhs->hidden_revenue : 0;
            $adminProfit = $visibleSalesRevenue - $visibleSalesCommission + $hiddenSalesRevenue;

            $summary = [
                'affiliate_id' => $uid,
                'name' => $user->full_name,
                'email' => $user->email,
                'total_clicks' => $uc ? (int)$uc->total_clicks : 0,
                'unique_clicks' => $uc ? (int)$uc->unique_clicks : 0,
                'countries_count' => $uc ? (int)$uc->countries_count : 0,
                'visible_sales' => $visibleSalesCount,
                'visible_revenue' => $visibleSalesRevenue,
                'affiliate_commission' => $visibleSalesCommission,
                'hidden_sales' => $hiddenSalesCount,
                'hidden_revenue' => $hiddenSalesRevenue,
                'admin_profit' => $adminProfit,
                'total_profit' => $adminProfit,
                'balance' => (float)$user->balance,
                'offers' => [],
            ];

            $offersClicks = $clicksByOffer->where('affiliate_id', $uid)->keyBy('game_id');
            $offersVisibleSales = $visibleSalesByOffer->where('affiliate_id', $uid)->keyBy('game_id');
            $offersHiddenSales = $hiddenSalesByOffer->where('affiliate_id', $uid)->keyBy('game_id');
            $gameIds = $offersClicks->keys()->merge($offersVisibleSales->keys())->merge($offersHiddenSales->keys())->unique();

            foreach ($gameIds as $gid) {
                $oc = $offersClicks->get($gid);
                $ovs = $offersVisibleSales->get($gid);
                $ohs = $offersHiddenSales->get($gid);
                $game = $games->get($gid);

                $visibleSalesCount = $ovs ? (int)$ovs->total_sales : 0;
                $visibleSalesRevenue = $ovs ? (float)$ovs->total_revenue : 0;
                $visibleSalesCommission = $ovs ? (float)$ovs->total_commission : 0;
                $hiddenSalesCount = $ohs ? (int)$ohs->hidden_sales : 0;
                $hiddenSalesRevenue = $ohs ? (float)$ohs->hidden_revenue : 0;
                $offerAdminProfit = $visibleSalesRevenue - $visibleSalesCommission + $hiddenSalesRevenue;

                $offerRow = [
                    'game_id' => $gid,
                    'game_name' => $game ? $game->name : 'Unknown',
                    'game_status' => $game ? $game->status : null,
                    'total_clicks' => $oc ? (int)$oc->total_clicks : 0,
                    'unique_clicks' => $oc ? (int)$oc->unique_clicks : 0,
                    'countries' => $oc && $oc->countries ? array_filter(explode(',', $oc->countries)) : [],
                    'visible_sales' => $visibleSalesCount,
                    'visible_revenue' => $visibleSalesRevenue,
                    'affiliate_commission' => $visibleSalesCommission,
                    'hidden_sales' => $hiddenSalesCount,
                    'hidden_revenue' => $hiddenSalesRevenue,
                    'admin_profit' => $offerAdminProfit,
                    'total_profit' => $offerAdminProfit,
                    'package_types' => $ovs && $ovs->package_types ? array_filter(explode(',', $ovs->package_types)) : [],
                    'subs' => [],
                ];

                $subClicks = $clicksBySub->where('affiliate_id', $uid)->where('game_id', $gid);
                $subVisibleSales = $visibleSalesBySub->where('affiliate_id', $uid)->where('game_id', $gid);

                $subMap = [];
                foreach ($subClicks as $sc) {
                    $key = implode('|', [$sc->sub1, $sc->sub2, $sc->sub3, $sc->sub4, $sc->sub5, $sc->sub6]);
                    $subMap[$key] = [
                        'sub1' => $sc->sub1, 'sub2' => $sc->sub2, 'sub3' => $sc->sub3,
                        'sub4' => $sc->sub4, 'sub5' => $sc->sub5, 'sub6' => $sc->sub6,
                        'total_clicks' => (int)$sc->total_clicks,
                        'unique_clicks' => (int)$sc->unique_clicks,
                        'countries' => $sc->countries ? array_filter(explode(',', $sc->countries)) : [],
                        'device_types' => $sc->device_types ? array_filter(explode(',', $sc->device_types)) : [],
                        'browsers' => $sc->browsers ? array_filter(explode(',', $sc->browsers)) : [],
                        'visible_sales' => 0, 'visible_revenue' => 0,
                        'affiliate_commission' => 0, 'hidden_sales' => 0,
                        'hidden_revenue' => 0, 'admin_profit' => 0,
                        'total_profit' => 0, 'package_types' => [],
                    ];
                }

                foreach ($subVisibleSales as $ss) {
                    $key = implode('|', [$ss->sub1, $ss->sub2, $ss->sub3, $ss->sub4, $ss->sub5, $ss->sub6]);
                    if (!isset($subMap[$key])) {
                        $subMap[$key] = [
                            'sub1' => $ss->sub1, 'sub2' => $ss->sub2, 'sub3' => $ss->sub3,
                            'sub4' => $ss->sub4, 'sub5' => $ss->sub5, 'sub6' => $ss->sub6,
                            'total_clicks' => 0, 'unique_clicks' => 0,
                            'countries' => [], 'device_types' => [], 'browsers' => [],
                        ];
                    }
                    $subMap[$key]['visible_sales'] = (int)$ss->total_sales;
                    $subMap[$key]['visible_revenue'] = (float)$ss->total_revenue;
                    $subMap[$key]['affiliate_commission'] = (float)$ss->total_commission;
                    $subMap[$key]['package_types'] = $ss->package_types ? array_filter(explode(',', $ss->package_types)) : [];
                    $subVisibleRevenue = (float)$ss->total_revenue;
                    $subCommission = (float)$ss->total_commission;
                    $subMap[$key]['admin_profit'] = $subVisibleRevenue - $subCommission;
                    $subMap[$key]['total_profit'] = $subVisibleRevenue - $subCommission;
                }

                $offerRow['subs'] = array_values($subMap);
                $summary['offers'][] = $offerRow;
            }

            return $summary;
        });

        // ── 10. Grand totals ───────────────────────────────────────────────────
        $grandClicks = (clone $clickBase)->select(
            DB::raw('COUNT(*) as total_clicks'),
            DB::raw('SUM(is_unique) as unique_clicks'),
            DB::raw('COUNT(DISTINCT affiliate_id) as affiliates_count'),
            DB::raw('COUNT(DISTINCT country) as countries_count')
        )->first();

        $grandVisibleSales = (clone $visibleSaleBase)->select(
            DB::raw('COUNT(*) as total_sales'),
            DB::raw('SUM(commission_amount) as total_commission'),
            DB::raw('SUM(package_price) as total_revenue')
        )->first();

        $grandHiddenSales = (clone $hiddenSaleBase)->select(
            DB::raw('COUNT(*) as hidden_sales'),
            DB::raw('SUM(package_price) as hidden_revenue')
        )->first();

        $grandAllSales = (clone $allSaleBase)->select(
            DB::raw('COUNT(*) as all_sales'),
            DB::raw('SUM(package_price) as all_revenue')
        )->first();

        $visibleRevenue = (float)($grandVisibleSales->total_revenue ?? 0);
        $visibleCommission = (float)($grandVisibleSales->total_commission ?? 0);
        $hiddenRevenue = (float)($grandHiddenSales->hidden_revenue ?? 0);

        $availableGames = GameManage::select('id', 'name')->active()->ordered()->get();
        $availableCountries = AffiliateClick::select('country')
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return response()->json([
            'success' => true,
            'grand_totals' => [
                'total_clicks' => (int)($grandClicks->total_clicks ?? 0),
                'unique_clicks' => (int)($grandClicks->unique_clicks ?? 0),
                'affiliates_count' => (int)($grandClicks->affiliates_count ?? 0),
                'countries_count' => (int)($grandClicks->countries_count ?? 0),
                'visible_sales' => (int)($grandVisibleSales->total_sales ?? 0),
                'visible_revenue' => $visibleRevenue,
                'affiliate_commission' => $visibleCommission,
                'hidden_sales' => (int)($grandHiddenSales->hidden_sales ?? 0),
                'hidden_revenue' => $hiddenRevenue,
                'admin_profit' => $visibleRevenue - $visibleCommission + $hiddenRevenue,
                'total_profit' => $visibleRevenue - $visibleCommission + $hiddenRevenue,
                'all_sales' => (int)($grandAllSales->all_sales ?? 0),
                'all_revenue' => (float)($grandAllSales->all_revenue ?? 0),
            ],
            'filters_meta' => [
                'games' => $availableGames,
                'countries' => $availableCountries,
            ],
            'pagination' => [
                'total' => $affiliates->total(),
                'per_page' => $affiliates->perPage(),
                'current_page' => $affiliates->currentPage(),
                'last_page' => $affiliates->lastPage(),
            ],
            'data' => $result,
        ]);
    }

    /**
     * GET /admin/affiliate-reports/countries
     */
    public function byCountry(Request $request)
    {
        $dateFrom   = $request->query('date_from');
        $dateTo     = $request->query('date_to');
        $filterAff  = $request->query('affiliate_id');
        $filterGame = $request->query('game_id');

        $rows = AffiliateClick::query()
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
            ->select('country',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(is_unique) as unique_clicks'),
                DB::raw('COUNT(DISTINCT affiliate_id) as affiliates_count')
            )
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * GET /admin/affiliate-reports/export
     */
    public function export(Request $request)
    {
        $dateFrom   = $request->query('date_from');
        $dateTo     = $request->query('date_to');
        $filterAff  = $request->query('affiliate_id');
        $filterGame = $request->query('game_id');

        $rows = AffiliateClick::query()
            ->join('users', 'affiliate_clicks.affiliate_id', '=', 'users.id')
            ->leftJoin('game_manages', 'affiliate_clicks.game_id', '=', 'game_manages.id')
            ->leftJoin('affiliate_sales', 'affiliate_clicks.id', '=', 'affiliate_sales.click_id')
            ->when($dateFrom, fn($q) => $q->whereDate('affiliate_clicks.created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('affiliate_clicks.created_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_clicks.affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('affiliate_clicks.game_id', $filterGame))
            ->select(
                'users.id as affiliate_id',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as affiliate_name"),
                'users.email as affiliate_email',
                'game_manages.name as game_name',
                'affiliate_clicks.sub1', 'affiliate_clicks.sub2',
                'affiliate_clicks.sub3', 'affiliate_clicks.sub4',
                'affiliate_clicks.sub5', 'affiliate_clicks.sub6',
                'affiliate_clicks.country', 'affiliate_clicks.device_type',
                'affiliate_clicks.browser', 'affiliate_clicks.is_unique',
                'affiliate_clicks.created_at as click_time',
                'affiliate_sales.package_type',
                'affiliate_sales.package_price',
                'affiliate_sales.commission_percentage',
                'affiliate_sales.commission_amount',
                'affiliate_sales.is_hidden',
                'affiliate_sales.status as sale_status',
                'affiliate_sales.purchased_at as sale_time'
            )
            ->orderBy('affiliate_clicks.created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="affiliate_report_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Affiliate ID', 'Name', 'Email', 'Game/Offer',
                'Sub1', 'Sub2', 'Sub3', 'Sub4', 'Sub5', 'Sub6',
                'Country', 'Device', 'Browser', 'Unique Click',
                'Click Time', 'Package', 'Price', 'Commission %',
                'Commission Amount', 'Is Hidden', 'Sale Status', 'Sale Time'
            ]);
            foreach ($rows as $row) {
                fputcsv($file, [
                    $row->affiliate_id, $row->affiliate_name, $row->affiliate_email,
                    $row->game_name,
                    $row->sub1, $row->sub2, $row->sub3, $row->sub4, $row->sub5, $row->sub6,
                    $row->country, $row->device_type, $row->browser,
                    $row->is_unique ? 'Yes' : 'No',
                    $row->click_time, $row->package_type, $row->package_price,
                    $row->commission_percentage, $row->commission_amount,
                    $row->is_hidden ? 'Yes (Admin Profit)' : 'No',
                    $row->sale_status, $row->sale_time,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * GET /admin/dashboard
     */
    public function adminDashboard(Request $request)
    {
        $dateFrom      = $request->query('date_from');
        $dateTo        = $request->query('date_to');
        $filterAff     = $request->query('affiliate_id');
        $filterGame    = $request->query('game_id');
        $filterCountry = $request->query('country');

        // ── Base queries ──────────────────────────────────────────────────────────
        $clickBase = AffiliateClick::query()
            ->when($dateFrom,      fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,        fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($filterAff,     fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame,    fn($q) => $q->where('game_id', $filterGame))
            ->when($filterCountry, fn($q) => $q->where('country', $filterCountry));

        $visibleSaleBase = AffiliateSale::query()
            ->where('is_hidden', false)
            ->when($dateFrom,   fn($q) => $q->whereDate('purchased_at', '>=', $dateFrom))
            ->when($dateTo,     fn($q) => $q->whereDate('purchased_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame));

        $hiddenSaleBase = AffiliateSale::query()
            ->where('is_hidden', true)
            ->when($dateFrom,   fn($q) => $q->whereDate('purchased_at', '>=', $dateFrom))
            ->when($dateTo,     fn($q) => $q->whereDate('purchased_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame));

        // ── 1. Grand totals ───────────────────────────────────────────────────────
        $grandClicks = (clone $clickBase)->selectRaw(
            'COUNT(*) as total_clicks,
             SUM(is_unique) as unique_clicks,
             COUNT(DISTINCT affiliate_id) as affiliates_count,
             COUNT(DISTINCT country) as countries_count'
        )->first();

        $grandVisibleSales = (clone $visibleSaleBase)->selectRaw(
            'COUNT(*) as visible_sales,
             SUM(commission_amount) as affiliate_commission,
             SUM(package_price) as visible_revenue'
        )->first();

        $grandHiddenSales = (clone $hiddenSaleBase)->selectRaw(
            'COUNT(*) as hidden_sales,
             SUM(package_price) as hidden_revenue'
        )->first();

        $visibleRevenue = (float)($grandVisibleSales->visible_revenue ?? 0);
        $affiliateCommission = (float)($grandVisibleSales->affiliate_commission ?? 0);
        $hiddenRevenue = (float)($grandHiddenSales->hidden_revenue ?? 0);
        $adminProfit = $visibleRevenue - $affiliateCommission + $hiddenRevenue;

        $userTotals = User::selectRaw(
            'SUM(balance) as total_balance,
             SUM(total_earnings) as total_earnings,
             COUNT(*) as total_affiliates'
        )->whereHas('roles', fn($q) => $q->where('name', 'affiliate'))
         ->first();

        $grandTotals = [
            'total_clicks' => (int)($grandClicks->total_clicks ?? 0),
            'unique_clicks' => (int)($grandClicks->unique_clicks ?? 0),
            'affiliates_count' => (int)($grandClicks->affiliates_count ?? 0),
            'countries_count' => (int)($grandClicks->countries_count ?? 0),
            'visible_sales' => (int)($grandVisibleSales->visible_sales ?? 0),
            'visible_revenue' => $visibleRevenue,
            'affiliate_commission' => $affiliateCommission,
            'hidden_sales' => (int)($grandHiddenSales->hidden_sales ?? 0),
            'hidden_revenue' => $hiddenRevenue,
            'admin_profit' => $adminProfit,
            'total_balance' => (float)($userTotals->total_balance ?? 0),
            'total_earnings' => (float)($userTotals->total_earnings ?? 0),
            'total_affiliates' => (int)($userTotals->total_affiliates ?? 0),
            'cr' => ($grandClicks->total_clicks ?? 0) > 0
                ? round(($grandVisibleSales->visible_sales / $grandClicks->total_clicks) * 100, 2)
                : 0,
        ];

        // ── 2. Period stats ───────────────────────────────────────────────────────
        $now = Carbon::now();
        $periods = [
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        ];

        $periodStats = [];
        foreach ($periods as $key => [$start, $end]) {
            $pc = AffiliateClick::whereBetween('created_at', [$start, $end])
                ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
                ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
                ->when($filterCountry, fn($q) => $q->where('country', $filterCountry))
                ->selectRaw('COUNT(*) as total, SUM(is_unique) as unique_count')
                ->first();

            $pvs = AffiliateSale::whereBetween('purchased_at', [$start, $end])
                ->where('is_hidden', false)
                ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
                ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
                ->selectRaw('COUNT(*) as visible_sales, SUM(commission_amount) as commission, SUM(package_price) as revenue')
                ->first();

            $phs = AffiliateSale::whereBetween('purchased_at', [$start, $end])
                ->where('is_hidden', true)
                ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
                ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
                ->selectRaw('COUNT(*) as hidden_sales, SUM(package_price) as hidden_revenue')
                ->first();

            $cl = (int)($pc->total ?? 0);
            $conv = (int)($pvs->visible_sales ?? 0);
            $visibleRevenuePeriod = (float)($pvs->revenue ?? 0);
            $affiliateCommissionPeriod = (float)($pvs->commission ?? 0);
            $hiddenRevenuePeriod = (float)($phs->hidden_revenue ?? 0);

            $periodStats[$key] = [
                'clicks' => $cl,
                'unique' => (int)($pc->unique_count ?? 0),
                'visible_sales' => $conv,
                'visible_revenue' => $visibleRevenuePeriod,
                'affiliate_commission' => $affiliateCommissionPeriod,
                'hidden_sales' => (int)($phs->hidden_sales ?? 0),
                'hidden_revenue' => $hiddenRevenuePeriod,
                'admin_profit' => $visibleRevenuePeriod - $affiliateCommissionPeriod + $hiddenRevenuePeriod,
                'cr' => $cl > 0 ? round(($conv / $cl) * 100, 2) : 0,
            ];
        }

        // ── 3. Top affiliates ─────────────────────────────────────────────────────
        $topAffiliateClicks = (clone $clickBase)
            ->select('affiliate_id', DB::raw('COUNT(*) as total_clicks'), DB::raw('SUM(is_unique) as unique_clicks'))
            ->groupBy('affiliate_id')
            ->get()->keyBy('affiliate_id');

        $topAffiliateVisibleSales = (clone $visibleSaleBase)
            ->select('affiliate_id',
                DB::raw('COUNT(*) as visible_sales'),
                DB::raw('SUM(commission_amount) as affiliate_commission'),
                DB::raw('SUM(package_price) as visible_revenue'))
            ->groupBy('affiliate_id')
            ->orderByDesc('affiliate_commission')
            ->limit(10)
            ->get()->keyBy('affiliate_id');

        $topAffiliateIds = $topAffiliateVisibleSales->keys()->merge($topAffiliateClicks->keys())->unique()->take(10);
        $affUsers = User::whereIn('id', $topAffiliateIds)->get()->keyBy('id');

        $topAffiliates = $topAffiliateIds->map(function ($uid) use ($affUsers, $topAffiliateClicks, $topAffiliateVisibleSales) {
            $u = $affUsers->get($uid);
            $ac = $topAffiliateClicks->get($uid);
            $avs = $topAffiliateVisibleSales->get($uid);
            $cl = (int)($ac->total_clicks ?? 0);
            $cv = (int)($avs->visible_sales ?? 0);
            $visibleRevenue = (float)($avs->visible_revenue ?? 0);
            $affiliateCommission = (float)($avs->affiliate_commission ?? 0);
            
            return [
                'affiliate_id' => $uid,
                'name' => $u ? $u->full_name : 'Unknown',
                'email' => $u ? $u->email : '',
                'balance' => $u ? (float)$u->balance : 0,
                'total_earnings' => $u ? (float)$u->total_earnings : 0,
                'total_clicks' => $cl,
                'unique_clicks' => (int)($ac->unique_clicks ?? 0),
                'visible_sales' => $cv,
                'visible_revenue' => $visibleRevenue,
                'affiliate_commission' => $affiliateCommission,
                'cr' => $cl > 0 ? round(($cv / $cl) * 100, 2) : 0,
            ];
        })->sortByDesc('affiliate_commission')->values()->toArray();

        // ── 4. Top countries ──────────────────────────────────────────────────────
        $countryClicks = (clone $clickBase)
            ->whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as total_clicks'), DB::raw('SUM(is_unique) as unique_clicks'))
            ->groupBy('country')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()->keyBy('country');

        $countryVisibleSales = (clone $visibleSaleBase)
            ->whereNotNull('customer_country')
            ->select('customer_country as country', 
                DB::raw('COUNT(*) as conversions'),
                DB::raw('SUM(commission_amount) as commission'),
                DB::raw('SUM(package_price) as revenue'))
            ->groupBy('customer_country')
            ->get()->keyBy('country');

        $topCountries = $countryClicks->map(function ($row, $country) use ($countryVisibleSales) {
            $s = $countryVisibleSales->get($country);
            $cl = (int)$row->total_clicks;
            $cv = (int)($s->conversions ?? 0);
            return [
                'country' => $country,
                'clicks' => $cl,
                'unique' => (int)$row->unique_clicks,
                'conversions' => $cv,
                'commission' => (float)($s->commission ?? 0),
                'revenue' => (float)($s->revenue ?? 0),
                'cr' => $cl > 0 ? round(($cv / $cl) * 100, 2) : 0,
            ];
        })->values()->toArray();

        // ── 5. Top games ──────────────────────────────────────────────────────────
        $gameClicks = (clone $clickBase)
            ->whereNotNull('game_id')
            ->select('game_id', DB::raw('COUNT(*) as total_clicks'), DB::raw('SUM(is_unique) as unique_clicks'))
            ->groupBy('game_id')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()->keyBy('game_id');

        $gameVisibleSales = (clone $visibleSaleBase)
            ->whereIn('game_id', $gameClicks->keys())
            ->select('game_id',
                DB::raw('COUNT(*) as conversions'),
                DB::raw('SUM(commission_amount) as commission'),
                DB::raw('SUM(package_price) as revenue'))
            ->groupBy('game_id')
            ->get()->keyBy('game_id');

        $gameNames = GameManage::whereIn('id', $gameClicks->keys())->pluck('name', 'id');

        $topGames = $gameClicks->map(function ($row, $gid) use ($gameVisibleSales, $gameNames) {
            $s = $gameVisibleSales->get($gid);
            $cl = (int)$row->total_clicks;
            $cv = (int)($s->conversions ?? 0);
            return [
                'game_id' => $gid,
                'game_name' => $gameNames->get($gid, 'Unknown'),
                'clicks' => $cl,
                'unique' => (int)$row->unique_clicks,
                'conversions' => $cv,
                'commission' => (float)($s->commission ?? 0),
                'revenue' => (float)($s->revenue ?? 0),
                'cr' => $cl > 0 ? round(($cv / $cl) * 100, 2) : 0,
            ];
        })->values()->toArray();

        // ── 6. Device & browser breakdown (last 7 days) ───────────────────────────
        $since7 = Carbon::now()->subDays(7);

        $deviceBreakdown = AffiliateClick::where('created_at', '>=', $since7)
            ->whereNotNull('device_type')
            ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
            ->select('device_type', DB::raw('COUNT(*) as count'))
            ->groupBy('device_type')->orderByDesc('count')
            ->get()->map(fn($r) => ['label' => $r->device_type, 'value' => (int)$r->count])->toArray();

        $browserBreakdown = AffiliateClick::where('created_at', '>=', $since7)
            ->whereNotNull('browser')
            ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
            ->select('browser', DB::raw('COUNT(*) as count'))
            ->groupBy('browser')->orderByDesc('count')
            ->get()->map(fn($r) => ['label' => $r->browser, 'value' => (int)$r->count])->toArray();

        // ── 7. Daily chart data (last 30 days) ───────────────────────────────────
       $since30 = Carbon::now()->subDays(30)->startOfDay();

$dailyClicks = AffiliateClick::where('created_at', '>=', $since30)
    ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
    ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
    ->when($filterCountry, fn($q) => $q->where('country', $filterCountry))
    ->selectRaw('DATE(created_at) as period, COUNT(*) as clicks, SUM(is_unique) as unique_clicks')
    ->groupBy('period')->orderBy('period')
    ->get()->keyBy('period');

$dailyVisibleSales = AffiliateSale::where('purchased_at', '>=', $since30)
    ->where('is_hidden', false)
    ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
    ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
    ->selectRaw('DATE(purchased_at) as period, COUNT(*) as conversions, SUM(commission_amount) as commission, SUM(package_price) as revenue')
    ->groupBy('period')->orderBy('period')
    ->get()->keyBy('period');

$dailyHiddenSales = AffiliateSale::where('purchased_at', '>=', $since30)
    ->where('is_hidden', true)
    ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
    ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
    ->selectRaw('DATE(purchased_at) as period, COUNT(*) as hidden_sales, SUM(package_price) as hidden_revenue')
    ->groupBy('period')->orderBy('period')
    ->get()->keyBy('period');

$chartData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = Carbon::now()->subDays($i)->format('Y-m-d');
    $dc = $dailyClicks->get($date);
    $dvs = $dailyVisibleSales->get($date);
    $dhs = $dailyHiddenSales->get($date);
    $cl = (int)($dc->clicks ?? 0);
    $cv = (int)($dvs->conversions ?? 0);
    $visibleRevenuePeriod = (float)($dvs->revenue ?? 0);
    $affiliateCommissionPeriod = (float)($dvs->commission ?? 0);
    $hiddenRevenuePeriod = (float)($dhs->hidden_revenue ?? 0);
    $hiddenSalesCount = (int)($dhs->hidden_sales ?? 0);
    
    $adminProfitOnlyVisible = $visibleRevenuePeriod - $affiliateCommissionPeriod;
    $adminProfitWithHidden = $adminProfitOnlyVisible + $hiddenRevenuePeriod;
    
    $chartData[] = [
        'label' => $date,
        'clicks' => $cl,
        'unique' => (int)($dc->unique_clicks ?? 0),
        'visible_sales' => $cv,
        'visible_revenue' => $visibleRevenuePeriod,
        'affiliate_commission' => $affiliateCommissionPeriod,
        'hidden_sales' => $hiddenSalesCount,
        'hidden_revenue' => $hiddenRevenuePeriod,
        'admin_profit' => $adminProfitWithHidden,  // For super-admin
        'admin_profit_visible_only' => $adminProfitOnlyVisible,  // For regular admin
    ];
}

        // ── 8. Performance insights ───────────────────────────────────────────────
        $bestDay = AffiliateSale::where('is_hidden', false)
            ->selectRaw('DATE(purchased_at) as period, SUM(commission_amount) as commission, COUNT(*) as conversions')
            ->groupBy('period')->orderByDesc('commission')->first();

        $bestWeek = AffiliateSale::where('is_hidden', false)
            ->selectRaw('YEARWEEK(purchased_at, 1) as period, SUM(commission_amount) as commission, COUNT(*) as conversions')
            ->groupBy('period')->orderByDesc('commission')->first();

        $bestMonth = AffiliateSale::where('is_hidden', false)
            ->selectRaw('DATE_FORMAT(purchased_at, "%Y-%m") as period, SUM(commission_amount) as commission, COUNT(*) as conversions')
            ->groupBy('period')->orderByDesc('commission')->first();

        $weekLabel = null;
        if ($bestWeek) {
            $yw = (string)$bestWeek->period;
            $weekLabel = substr($yw, 0, 4) . '-W' . substr($yw, 4, 2);
        }
        $monthLabel = $bestMonth ? Carbon::createFromFormat('Y-m', $bestMonth->period)->format('F Y') : null;

        $since90 = Carbon::now()->subDays(90);
        $days90cl = AffiliateClick::where('created_at', '>=', $since90)->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->get();
        $days90sl = AffiliateSale::where('purchased_at', '>=', $since90)->where('is_hidden', false)->selectRaw('DATE(purchased_at) as d, COUNT(*) as conversions, SUM(commission_amount) as commission')->groupBy('d')->get();
        $dayCount = max($days90cl->count(), 1);

        $performanceInsights = [
            'best_day' => $bestDay ? ['period' => (string)$bestDay->period, 'commission' => (float)$bestDay->commission, 'conversions' => (int)$bestDay->conversions] : null,
            'best_week' => $bestWeek ? ['period' => $weekLabel, 'commission' => (float)$bestWeek->commission, 'conversions' => (int)$bestWeek->conversions] : null,
            'best_month' => $bestMonth ? ['period' => $monthLabel, 'commission' => (float)$bestMonth->commission, 'conversions' => (int)$bestMonth->conversions] : null,
            'daily_averages' => [
                'clicks' => round($days90cl->sum('c') / $dayCount, 1),
                'conversions' => round($days90sl->sum('conversions') / $dayCount, 1),
                'commission' => round($days90sl->sum('commission') / $dayCount, 2),
            ],
        ];

        // ── 9. Filters meta ───────────────────────────────────────────────────────
        $availableGames = GameManage::select('id', 'name')->active()->ordered()->get();
        $availableCountries = AffiliateClick::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');
        $availableAffiliates = User::whereHas('roles', fn($q) => $q->where('name', 'affiliate'))
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'email')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'success' => true,
            'grand_totals' => $grandTotals,
            'period_stats' => $periodStats,
            'top_affiliates' => $topAffiliates,
            'top_countries' => $topCountries,
            'top_games' => $topGames,
            'device_breakdown' => $deviceBreakdown,
            'browser_breakdown' => $browserBreakdown,
            'chart_data' => $chartData,
            'performance_insights' => $performanceInsights,
            'filters_meta' => [
                'games' => $availableGames,
                'countries' => $availableCountries,
                'affiliates' => $availableAffiliates,
            ],
        ]);
    }
}