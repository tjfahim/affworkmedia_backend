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
     *
     * Query params (all optional):
     *   date_from      – Y-m-d
     *   date_to        – Y-m-d
     *   affiliate_id   – filter to one user
     *   game_id        – filter to one offer/game
     *   country        – filter by country code
     *   per_page       – paginate users (default 20)
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

        // ── 2. Build base sale query ─────────────────────────────────────────
        $saleBase = AffiliateSale::query()
            ->when($dateFrom, fn($q) => $q->whereDate('purchased_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('purchased_at', '<=', $dateTo))
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame));

        // ── 3. Aggregate clicks per affiliate ────────────────────────────────
        $clicksByAffiliate = (clone $clickBase)
            ->select(
                'affiliate_id',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(is_unique) as unique_clicks'),
                DB::raw('COUNT(DISTINCT country) as countries_count')
            )
            ->groupBy('affiliate_id')
            ->get()
            ->keyBy('affiliate_id');

        // ── 4. Aggregate sales per affiliate ─────────────────────────────────
        $salesByAffiliate = (clone $saleBase)
            ->select(
                'affiliate_id',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(commission_amount) as total_commission'),
                DB::raw('SUM(package_price) as total_revenue')
            )
            ->groupBy('affiliate_id')
            ->get()
            ->keyBy('affiliate_id');

        // ── 5. Get distinct affiliate IDs involved ────────────────────────────
        $affiliateIds = $clicksByAffiliate->keys()
            ->merge($salesByAffiliate->keys())
            ->unique();

        // ── 6. Load affiliate users ───────────────────────────────────────────
        $usersQuery = User::whereIn('id', $affiliateIds)
            ->when($filterAff, fn($q) => $q->where('id', $filterAff));

        $total      = $usersQuery->count();
        $affiliates = $usersQuery->orderBy('id')->paginate($perPage);

        // ── 7. For each affiliate: load offer-level breakdown ─────────────────
        $affiliateIds_page = $affiliates->pluck('id');

        $clicksByOffer = (clone $clickBase)
            ->whereIn('affiliate_id', $affiliateIds_page)
            ->select(
                'affiliate_id', 'game_id',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(is_unique) as unique_clicks'),
                DB::raw('COUNT(DISTINCT country) as countries_count'),
                DB::raw('GROUP_CONCAT(DISTINCT country ORDER BY country SEPARATOR ",") as countries')
            )
            ->groupBy('affiliate_id', 'game_id')
            ->get();

        $salesByOffer = (clone $saleBase)
            ->whereIn('affiliate_id', $affiliateIds_page)
            ->select(
                'affiliate_id', 'game_id',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(commission_amount) as total_commission'),
                DB::raw('SUM(package_price) as total_revenue'),
                DB::raw('GROUP_CONCAT(DISTINCT package_type ORDER BY package_type SEPARATOR ",") as package_types')
            )
            ->groupBy('affiliate_id', 'game_id')
            ->get();

        // ── 8. For each affiliate+offer: sub-account breakdown ────────────────
        // We treat (sub1, sub2) combination as sub identity
        $clicksBySub = (clone $clickBase)
            ->whereIn('affiliate_id', $affiliateIds_page)
            ->select(
                'affiliate_id', 'game_id',
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

        $salesBySub = (clone $saleBase)
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

        // ── 9. Load games map ─────────────────────────────────────────────────
        $gameIds = $clicksByOffer->pluck('game_id')->merge($salesByOffer->pluck('game_id'))->unique()->filter();
        $games   = GameManage::whereIn('id', $gameIds)->get()->keyBy('id');

        // ── 10. Assemble response ─────────────────────────────────────────────
        $result = $affiliates->map(function ($user) use (
            $clicksByAffiliate, $salesByAffiliate,
            $clicksByOffer, $salesByOffer,
            $clicksBySub, $salesBySub,
            $games
        ) {
            $uid = $user->id;

            // User summary
            $uc = $clicksByAffiliate->get($uid);
            $us = $salesByAffiliate->get($uid);

            $summary = [
                'affiliate_id'     => $uid,
                'name'             => $user->full_name,
                'email'            => $user->email,
                'total_clicks'     => $uc ? (int)$uc->total_clicks    : 0,
                'unique_clicks'    => $uc ? (int)$uc->unique_clicks   : 0,
                'countries_count'  => $uc ? (int)$uc->countries_count : 0,
                'total_sales'      => $us ? (int)$us->total_sales     : 0,
                'total_revenue'    => $us ? (float)$us->total_revenue     : 0,
                'total_commission' => $us ? (float)$us->total_commission  : 0,
                'balance'          => (float)$user->balance,
                'offers'           => [],
            ];

            // Offers for this affiliate
            $offersClicks = $clicksByOffer->where('affiliate_id', $uid)->keyBy('game_id');
            $offersSales  = $salesByOffer->where('affiliate_id', $uid)->keyBy('game_id');
            $gameIds      = $offersClicks->keys()->merge($offersSales->keys())->unique();

            foreach ($gameIds as $gid) {
                $oc = $offersClicks->get($gid);
                $os = $offersSales->get($gid);
                $game = $games->get($gid);

                $offerRow = [
                    'game_id'          => $gid,
                    'game_name'        => $game ? $game->name : 'Unknown',
                    'game_status'      => $game ? $game->status : null,
                    'total_clicks'     => $oc ? (int)$oc->total_clicks    : 0,
                    'unique_clicks'    => $oc ? (int)$oc->unique_clicks   : 0,
                    'countries'        => $oc && $oc->countries ? array_filter(explode(',', $oc->countries)) : [],
                    'total_sales'      => $os ? (int)$os->total_sales     : 0,
                    'total_revenue'    => $os ? (float)$os->total_revenue     : 0,
                    'total_commission' => $os ? (float)$os->total_commission  : 0,
                    'package_types'    => $os && $os->package_types ? array_filter(explode(',', $os->package_types)) : [],
                    'subs'             => [],
                ];

                // Sub rows for this affiliate + offer
                $subClicks = $clicksBySub->where('affiliate_id', $uid)->where('game_id', $gid);
                $subSales  = $salesBySub->where('affiliate_id', $uid)->where('game_id', $gid);

                // Build sub key map for subs from clicks
                $subMap = [];
                foreach ($subClicks as $sc) {
                    $key = implode('|', [$sc->sub1, $sc->sub2, $sc->sub3, $sc->sub4, $sc->sub5, $sc->sub6]);
                    $subMap[$key] = [
                        'sub1' => $sc->sub1,
                        'sub2' => $sc->sub2,
                        'sub3' => $sc->sub3,
                        'sub4' => $sc->sub4,
                        'sub5' => $sc->sub5,
                        'sub6' => $sc->sub6,
                        'total_clicks'    => (int)$sc->total_clicks,
                        'unique_clicks'   => (int)$sc->unique_clicks,
                        'countries'       => $sc->countries ? array_filter(explode(',', $sc->countries)) : [],
                        'device_types'    => $sc->device_types ? array_filter(explode(',', $sc->device_types)) : [],
                        'browsers'        => $sc->browsers ? array_filter(explode(',', $sc->browsers)) : [],
                        'total_sales'      => 0,
                        'total_revenue'    => 0,
                        'total_commission' => 0,
                        'package_types'    => [],
                    ];
                }

                // Merge sales into sub map
                foreach ($subSales as $ss) {
                    $key = implode('|', [$ss->sub1, $ss->sub2, $ss->sub3, $ss->sub4, $ss->sub5, $ss->sub6]);
                    if (!isset($subMap[$key])) {
                        $subMap[$key] = [
                            'sub1' => $ss->sub1, 'sub2' => $ss->sub2,
                            'sub3' => $ss->sub3, 'sub4' => $ss->sub4,
                            'sub5' => $ss->sub5, 'sub6' => $ss->sub6,
                            'total_clicks' => 0, 'unique_clicks' => 0,
                            'countries' => [], 'device_types' => [], 'browsers' => [],
                        ];
                    }
                    $subMap[$key]['total_sales']      = (int)$ss->total_sales;
                    $subMap[$key]['total_revenue']    = (float)$ss->total_revenue;
                    $subMap[$key]['total_commission'] = (float)$ss->total_commission;
                    $subMap[$key]['package_types']    = $ss->package_types ? array_filter(explode(',', $ss->package_types)) : [];
                }

                $offerRow['subs'] = array_values($subMap);
                $summary['offers'][] = $offerRow;
            }

            return $summary;
        });

        // ── 11. Grand totals ──────────────────────────────────────────────────
        $grandClicks = (clone $clickBase)->select(
            DB::raw('COUNT(*) as total_clicks'),
            DB::raw('SUM(is_unique) as unique_clicks'),
            DB::raw('COUNT(DISTINCT affiliate_id) as affiliates_count'),
            DB::raw('COUNT(DISTINCT country) as countries_count')
        )->first();

        $grandSales = (clone $saleBase)->select(
            DB::raw('COUNT(*) as total_sales'),
            DB::raw('SUM(commission_amount) as total_commission'),
            DB::raw('SUM(package_price) as total_revenue')
        )->first();

        // ── 12. Filters meta ──────────────────────────────────────────────────
        $availableGames = GameManage::select('id', 'name')->active()->ordered()->get();

        $availableCountries = AffiliateClick::select('country')
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return response()->json([
            'success' => true,
            'grand_totals' => [
                'total_clicks'      => (int)($grandClicks->total_clicks ?? 0),
                'unique_clicks'     => (int)($grandClicks->unique_clicks ?? 0),
                'affiliates_count'  => (int)($grandClicks->affiliates_count ?? 0),
                'countries_count'   => (int)($grandClicks->countries_count ?? 0),
                'total_sales'       => (int)($grandSales->total_sales ?? 0),
                'total_revenue'     => (float)($grandSales->total_revenue ?? 0),
                'total_commission'  => (float)($grandSales->total_commission ?? 0),
            ],
            'filters_meta' => [
                'games'     => $availableGames,
                'countries' => $availableCountries,
            ],
            'pagination' => [
                'total'        => $affiliates->total(),
                'per_page'     => $affiliates->perPage(),
                'current_page' => $affiliates->currentPage(),
                'last_page'    => $affiliates->lastPage(),
            ],
            'data' => $result,
        ]);
    }

    /**
     * GET /admin/affiliate-reports/countries
     * Returns per-country breakdown across all affiliates (for country-level drill-down).
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
            ->select(
                'country',
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
     * CSV export of the full report.
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
                'affiliate_sales.status as sale_status',
                'affiliate_sales.purchased_at as sale_time'
            )
            ->orderBy('affiliate_clicks.created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="affiliate_report_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Affiliate ID', 'Name', 'Email', 'Game/Offer',
                'Sub1', 'Sub2', 'Sub3', 'Sub4', 'Sub5', 'Sub6',
                'Country', 'Device', 'Browser', 'Unique Click',
                'Click Time', 'Package', 'Price', 'Commission %',
                'Commission Amount', 'Sale Status', 'Sale Time'
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
                    $row->sale_status, $row->sale_time,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }









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
 
    $saleBase = AffiliateSale::query()
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
 
    $grandSales = (clone $saleBase)->selectRaw(
        'COUNT(*) as total_sales,
         SUM(commission_amount) as total_commission,
         SUM(package_price) as total_revenue'
    )->first();
 
    // Balance & earnings from users table
    $userTotals = User::selectRaw(
        'SUM(balance) as total_balance,
         SUM(total_earnings) as total_earnings,
         COUNT(*) as total_affiliates'
    )->whereHas('roles', fn($q) => $q->where('name', 'affiliate'))
     ->first();
 
    $grandTotals = [
        'total_clicks'      => (int)   ($grandClicks->total_clicks     ?? 0),
        'unique_clicks'     => (int)   ($grandClicks->unique_clicks    ?? 0),
        'affiliates_count'  => (int)   ($grandClicks->affiliates_count ?? 0),
        'countries_count'   => (int)   ($grandClicks->countries_count  ?? 0),
        'total_sales'       => (int)   ($grandSales->total_sales       ?? 0),
        'total_revenue'     => (float) ($grandSales->total_revenue     ?? 0),
        'total_commission'  => (float) ($grandSales->total_commission  ?? 0),
        'total_balance'     => (float) ($userTotals->total_balance     ?? 0),
        'total_earnings'    => (float) ($userTotals->total_earnings    ?? 0),
        'total_affiliates'  => (int)   ($userTotals->total_affiliates  ?? 0),
        'cr'                => ($grandClicks->total_clicks ?? 0) > 0
                                ? round(($grandSales->total_sales / $grandClicks->total_clicks) * 100, 2)
                                : 0,
    ];
 
    // ── 2. Period stats ───────────────────────────────────────────────────────
    $now = Carbon::now();
    $periods = [
        'today'      => [$now->copy()->startOfDay(),          $now->copy()->endOfDay()],
        'yesterday'  => [$now->copy()->subDay()->startOfDay(),$now->copy()->subDay()->endOfDay()],
        'this_week'  => [$now->copy()->startOfWeek(),         $now->copy()->endOfWeek()],
        'last_week'  => [$now->copy()->subWeek()->startOfWeek(),$now->copy()->subWeek()->endOfWeek()],
        'this_month' => [$now->copy()->startOfMonth(),        $now->copy()->endOfMonth()],
    ];
 
    $periodStats = [];
    foreach ($periods as $key => [$start, $end]) {
        $pc = AffiliateClick::whereBetween('created_at', [$start, $end])
            ->when($filterAff,     fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame,    fn($q) => $q->where('game_id', $filterGame))
            ->when($filterCountry, fn($q) => $q->where('country', $filterCountry))
            ->selectRaw('COUNT(*) as total, SUM(is_unique) as unique_count')
            ->first();
 
        $ps = AffiliateSale::whereBetween('purchased_at', [$start, $end])
            ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
            ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
            ->selectRaw('COUNT(*) as total_sales, SUM(commission_amount) as commission, SUM(package_price) as revenue')
            ->first();
 
        $cl   = (int)   ($pc->total       ?? 0);
        $conv = (int)   ($ps->total_sales ?? 0);
 
        $periodStats[$key] = [
            'clicks'      => $cl,
            'unique'      => (int)   ($pc->unique_count ?? 0),
            'conversions' => $conv,
            'commission'  => (float) ($ps->commission   ?? 0),
            'revenue'     => (float) ($ps->revenue      ?? 0),
            'cr'          => $cl > 0 ? round(($conv / $cl) * 100, 2) : 0,
        ];
    }
 
    // ── 3. Top affiliates ─────────────────────────────────────────────────────
    $topAffiliateClicks = (clone $clickBase)
        ->select('affiliate_id', DB::raw('COUNT(*) as total_clicks'), DB::raw('SUM(is_unique) as unique_clicks'))
        ->groupBy('affiliate_id')
        ->get()->keyBy('affiliate_id');
 
    $topAffiliateSales = (clone $saleBase)
        ->select('affiliate_id',
            DB::raw('COUNT(*) as total_sales'),
            DB::raw('SUM(commission_amount) as total_commission'),
            DB::raw('SUM(package_price) as total_revenue'))
        ->groupBy('affiliate_id')
        ->orderByDesc('total_commission')
        ->limit(10)
        ->get()->keyBy('affiliate_id');
 
    $topAffiliateIds = $topAffiliateSales->keys()->merge($topAffiliateClicks->keys())->unique()->take(10);
    $affUsers = User::whereIn('id', $topAffiliateIds)->get()->keyBy('id');
 
    $topAffiliates = $topAffiliateIds->map(function ($uid) use ($affUsers, $topAffiliateClicks, $topAffiliateSales) {
        $u  = $affUsers->get($uid);
        $ac = $topAffiliateClicks->get($uid);
        $as = $topAffiliateSales->get($uid);
        $cl = (int) ($ac->total_clicks ?? 0);
        $cv = (int) ($as->total_sales  ?? 0);
        return [
            'affiliate_id'     => $uid,
            'name'             => $u ? $u->full_name : 'Unknown',
            'email'            => $u ? $u->email     : '',
            'balance'          => $u ? (float) $u->balance         : 0,
            'total_earnings'   => $u ? (float) $u->total_earnings  : 0,
            'total_clicks'     => $cl,
            'unique_clicks'    => (int) ($ac->unique_clicks   ?? 0),
            'total_sales'      => $cv,
            'total_commission' => (float) ($as->total_commission ?? 0),
            'total_revenue'    => (float) ($as->total_revenue   ?? 0),
            'cr'               => $cl > 0 ? round(($cv / $cl) * 100, 2) : 0,
        ];
    })->sortByDesc('total_commission')->values()->toArray();
 
    // ── 4. Top countries ──────────────────────────────────────────────────────
    $countryClicks = (clone $clickBase)
        ->whereNotNull('country')
        ->select('country', DB::raw('COUNT(*) as total_clicks'), DB::raw('SUM(is_unique) as unique_clicks'))
        ->groupBy('country')
        ->orderByDesc('total_clicks')
        ->limit(10)
        ->get()->keyBy('country');
 
    $countrySales = (clone $saleBase)
        ->whereNotNull('customer_country')
        ->select('customer_country as country', DB::raw('COUNT(*) as conversions'), DB::raw('SUM(commission_amount) as commission'))
        ->groupBy('customer_country')
        ->get()->keyBy('country');
 
    $topCountries = $countryClicks->map(function ($row, $country) use ($countrySales) {
        $s  = $countrySales->get($country);
        $cl = (int) $row->total_clicks;
        $cv = (int) ($s->conversions ?? 0);
        return [
            'country'     => $country,
            'clicks'      => $cl,
            'unique'      => (int) $row->unique_clicks,
            'conversions' => $cv,
            'commission'  => (float) ($s->commission ?? 0),
            'cr'          => $cl > 0 ? round(($cv / $cl) * 100, 2) : 0,
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
 
    $gameSales = (clone $saleBase)
        ->whereIn('game_id', $gameClicks->keys())
        ->select('game_id', DB::raw('COUNT(*) as conversions'), DB::raw('SUM(commission_amount) as commission'), DB::raw('SUM(package_price) as revenue'))
        ->groupBy('game_id')
        ->get()->keyBy('game_id');
 
    $gameNames = GameManage::whereIn('id', $gameClicks->keys())->pluck('name', 'id');
 
    $topGames = $gameClicks->map(function ($row, $gid) use ($gameSales, $gameNames) {
        $s  = $gameSales->get($gid);
        $cl = (int) $row->total_clicks;
        $cv = (int) ($s->conversions ?? 0);
        return [
            'game_id'    => $gid,
            'game_name'  => $gameNames->get($gid, 'Unknown'),
            'clicks'     => $cl,
            'unique'     => (int) $row->unique_clicks,
            'conversions'=> $cv,
            'commission' => (float) ($s->commission ?? 0),
            'revenue'    => (float) ($s->revenue    ?? 0),
            'cr'         => $cl > 0 ? round(($cv / $cl) * 100, 2) : 0,
        ];
    })->values()->toArray();
 
    // ── 6. Device & browser breakdown (last 7 days) ───────────────────────────
    $since7 = Carbon::now()->subDays(7);
 
    $deviceBreakdown = AffiliateClick::where('created_at', '>=', $since7)
        ->whereNotNull('device_type')
        ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
        ->select('device_type', DB::raw('COUNT(*) as count'))
        ->groupBy('device_type')->orderByDesc('count')
        ->get()->map(fn($r) => ['label' => $r->device_type, 'value' => (int) $r->count])->toArray();
 
    $browserBreakdown = AffiliateClick::where('created_at', '>=', $since7)
        ->whereNotNull('browser')
        ->when($filterAff, fn($q) => $q->where('affiliate_id', $filterAff))
        ->select('browser', DB::raw('COUNT(*) as count'))
        ->groupBy('browser')->orderByDesc('count')
        ->get()->map(fn($r) => ['label' => $r->browser, 'value' => (int) $r->count])->toArray();
 
    // ── 7. Daily chart data (last 30 days) ───────────────────────────────────
    $since30 = Carbon::now()->subDays(30)->startOfDay();
 
    $dailyClicks = AffiliateClick::where('created_at', '>=', $since30)
        ->when($filterAff,     fn($q) => $q->where('affiliate_id', $filterAff))
        ->when($filterGame,    fn($q) => $q->where('game_id', $filterGame))
        ->when($filterCountry, fn($q) => $q->where('country', $filterCountry))
        ->selectRaw('DATE(created_at) as period, COUNT(*) as clicks, SUM(is_unique) as unique_clicks')
        ->groupBy('period')->orderBy('period')
        ->get()->keyBy('period');
 
    $dailySales = AffiliateSale::where('purchased_at', '>=', $since30)
        ->when($filterAff,  fn($q) => $q->where('affiliate_id', $filterAff))
        ->when($filterGame, fn($q) => $q->where('game_id', $filterGame))
        ->selectRaw('DATE(purchased_at) as period, COUNT(*) as conversions, SUM(commission_amount) as commission, SUM(package_price) as revenue')
        ->groupBy('period')->orderBy('period')
        ->get()->keyBy('period');
 
    // Merge into complete date range (fill gaps with 0)
    $chartData = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = Carbon::now()->subDays($i)->format('Y-m-d');
        $dc   = $dailyClicks->get($date);
        $ds   = $dailySales->get($date);
        $cl   = (int)   ($dc->clicks      ?? 0);
        $cv   = (int)   ($ds->conversions ?? 0);
        $chartData[] = [
            'label'       => $date,
            'clicks'      => $cl,
            'unique'      => (int)   ($dc->unique_clicks ?? 0),
            'conversions' => $cv,
            'commission'  => (float) ($ds->commission   ?? 0),
            'revenue'     => (float) ($ds->revenue      ?? 0),
            'cr'          => $cl > 0 ? round(($cv / $cl) * 100, 2) : 0,
        ];
    }
 
    // ── 8. Performance insights ───────────────────────────────────────────────
    $bestDay = AffiliateSale::selectRaw('DATE(purchased_at) as period, SUM(commission_amount) as commission, COUNT(*) as conversions')
        ->groupBy('period')->orderByDesc('commission')->first();
 
    $bestWeek = AffiliateSale::selectRaw('YEARWEEK(purchased_at, 1) as period, SUM(commission_amount) as commission, COUNT(*) as conversions')
        ->groupBy('period')->orderByDesc('commission')->first();
 
    $bestMonth = AffiliateSale::selectRaw('DATE_FORMAT(purchased_at, "%Y-%m") as period, SUM(commission_amount) as commission, COUNT(*) as conversions')
        ->groupBy('period')->orderByDesc('commission')->first();
 
    $weekLabel = null;
    if ($bestWeek) {
        $yw = (string) $bestWeek->period;
        $weekLabel = substr($yw, 0, 4) . '-W' . substr($yw, 4, 2);
    }
    $monthLabel = $bestMonth
        ? Carbon::createFromFormat('Y-m', $bestMonth->period)->format('F Y')
        : null;
 
    // Daily averages last 90 days
    $since90    = Carbon::now()->subDays(90);
    $days90cl   = AffiliateClick::where('created_at', '>=', $since90)->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->get();
    $days90sl   = AffiliateSale::where('purchased_at', '>=', $since90)->selectRaw('DATE(purchased_at) as d, COUNT(*) as conversions, SUM(commission_amount) as commission')->groupBy('d')->get();
    $dayCount   = max($days90cl->count(), 1);
 
    $performanceInsights = [
        'best_day'   => $bestDay   ? ['period' => (string) $bestDay->period,   'commission' => (float) $bestDay->commission,   'conversions' => (int) $bestDay->conversions]   : null,
        'best_week'  => $bestWeek  ? ['period' => $weekLabel,                  'commission' => (float) $bestWeek->commission,  'conversions' => (int) $bestWeek->conversions]  : null,
        'best_month' => $bestMonth ? ['period' => $monthLabel,                 'commission' => (float) $bestMonth->commission, 'conversions' => (int) $bestMonth->conversions] : null,
        'daily_averages' => [
            'clicks'      => round($days90cl->sum('c') / $dayCount, 1),
            'conversions' => round($days90sl->sum('conversions') / $dayCount, 1),
            'commission'  => round($days90sl->sum('commission') / $dayCount, 2),
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
        'success'              => true,
        'grand_totals'         => $grandTotals,
        'period_stats'         => $periodStats,
        'top_affiliates'       => $topAffiliates,
        'top_countries'        => $topCountries,
        'top_games'            => $topGames,
        'device_breakdown'     => $deviceBreakdown,
        'browser_breakdown'    => $browserBreakdown,
        'chart_data'           => $chartData,
        'performance_insights' => $performanceInsights,
        'filters_meta'         => [
            'games'      => $availableGames,
            'countries'  => $availableCountries,
            'affiliates' => $availableAffiliates,
        ],
    ]);
}
}