<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AffiliateClick;
use App\Models\AffiliateSale;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class AffiliateDashboardController extends Controller
{
    /**
     * GET /affiliate/dashboard
     * Master endpoint — returns all dashboard data in one call.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $uid  = $user->id;

        return response()->json([
            'success'            => true,
            'user'               => $this->userSummary($user),
            'period_stats'       => $this->periodStats($uid),
            'top_countries'      => $this->topCountries($uid),
            'top_games'          => $this->topGames($uid),
            'device_breakdown'   => $this->deviceBreakdown($uid),
            'os_breakdown'       => $this->osBreakdown($uid),
            'performance_insights' => $this->performanceInsights($uid),
        ]);
    }

    /**
     * GET /affiliate/dashboard/chart
     * Chart data — daily | weekly | monthly
     * Query: ?period=daily|weekly|monthly&range=30 (days to look back)
     */
    public function chart(Request $request)
    {
        $uid    = Auth::id();
        $period = $request->query('period', 'daily');   // daily | weekly | monthly
        $range  = (int) $request->query('range', 30);   // days to look back

        $data = $this->chartData($uid, $period, $range);

        return response()->json(['success' => true, 'data' => $data, 'period' => $period]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ────────────────────────────────────────────────────────────────────────

    private function userSummary(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->full_name,
            'email'          => $user->email,
            'balance'        => (float) $user->balance,
            'total_earnings' => (float) $user->total_earnings,
            'total_sales'    => (int)   $user->total_sales,
            'total_clicks'   => (int)   $user->total_clicks,
            'unique_clicks'  => (int)   $user->unique_clicks,
        ];
    }

    /** Stats for: today | yesterday | this_week | last_week | this_month */
    private function periodStats(int $uid): array
    {
        $now = Carbon::now();

        $periods = [
            'today'      => [$now->copy()->startOfDay(),    $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(),   $now->copy()->endOfWeek()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(),  $now->copy()->endOfMonth()],
        ];

        $result = [];

        foreach ($periods as $key => [$start, $end]) {
            $clicks = AffiliateClick::where('affiliate_id', $uid)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COUNT(*) as total, SUM(is_unique) as unique_count')
                ->first();

            $sales = AffiliateSale::where('affiliate_id', $uid)
                ->whereBetween('purchased_at', [$start, $end])
                ->selectRaw('COUNT(*) as total_sales, SUM(commission_amount) as profit')
                ->first();

            $totalClicks  = (int)   ($clicks->total        ?? 0);
            $uniqueClicks = (int)   ($clicks->unique_count ?? 0);
            $totalSales   = (int)   ($sales->total_sales   ?? 0);
            $profit       = (float) ($sales->profit        ?? 0);

            $result[$key] = [
                'clicks'      => $totalClicks,
                'unique'      => $uniqueClicks,
                'conversions' => $totalSales,
                'profit'      => $profit,
                'cr'          => $totalClicks > 0 ? round(($totalSales / $totalClicks) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    /** Top 10 countries by clicks */
    private function topCountries(int $uid): array
    {
        $clicks = AffiliateClick::where('affiliate_id', $uid)
            ->whereNotNull('country')
            ->select(
                'country',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(is_unique) as unique_clicks')
            )
            ->groupBy('country')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->keyBy('country');

        $sales = AffiliateSale::where('affiliate_id', $uid)
            ->whereNotNull('customer_country')
            ->select(
                'customer_country as country',
                DB::raw('COUNT(*) as conversions'),
                DB::raw('SUM(commission_amount) as profit')
            )
            ->groupBy('customer_country')
            ->get()
            ->keyBy('country');

        $result = [];
        $rank   = 1;

        foreach ($clicks as $country => $row) {
            $s = $sales->get($country);
            $conversions = (int)   ($s->conversions ?? 0);
            $totalClicks = (int)   $row->total_clicks;

            $result[] = [
                'rank'        => $rank++,
                'country'     => $country,
                'clicks'      => $totalClicks,
                'unique'      => (int) $row->unique_clicks,
                'conversions' => $conversions,
                'profit'      => (float) ($s->profit ?? 0),
                'cr'          => $totalClicks > 0 ? round(($conversions / $totalClicks) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    /** Top performing games with sub breakdown */
 private function topGames(int $uid): array
{
    // Game-level clicks
    $gameClicks = AffiliateClick::where('affiliate_id', $uid)
        ->whereNotNull('game_id')
        ->select(
            'game_id',
            DB::raw('COUNT(*) as total_clicks'),
            DB::raw('SUM(is_unique) as unique_clicks')
        )
        ->groupBy('game_id')
        ->orderByDesc('total_clicks')
        ->limit(10)
        ->get()
        ->keyBy('game_id');

    $gameIds = $gameClicks->keys();

    $gameSales = AffiliateSale::where('affiliate_id', $uid)
        ->whereIn('game_id', $gameIds)
        ->select(
            'game_id',
            DB::raw('COUNT(*) as conversions'),
            DB::raw('SUM(commission_amount) as profit')
        )
        ->groupBy('game_id')
        ->get()
        ->keyBy('game_id');

    // Sub-level breakdown per game
    $subClicks = AffiliateClick::where('affiliate_id', $uid)
        ->whereIn('game_id', $gameIds)
        ->select(
            'game_id', 'sub1', 'sub2',
            DB::raw('COUNT(*) as total_clicks'),
            DB::raw('SUM(is_unique) as unique_clicks')
        )
        ->groupBy('game_id', 'sub1', 'sub2')
        ->get();

    // FIXED: Specify which table's affiliate_id to use
    $subSales = AffiliateSale::where('affiliate_sales.affiliate_id', $uid)  // Changed here
        ->join('affiliate_clicks', 'affiliate_sales.click_id', '=', 'affiliate_clicks.id')
        ->whereIn('affiliate_sales.game_id', $gameIds)
        ->select(
            'affiliate_sales.game_id',
            'affiliate_clicks.sub1',
            'affiliate_clicks.sub2',
            DB::raw('COUNT(*) as conversions'),
            DB::raw('SUM(affiliate_sales.commission_amount) as profit')
        )
        ->groupBy('affiliate_sales.game_id', 'affiliate_clicks.sub1', 'affiliate_clicks.sub2')
        ->get();

    // Load game names
    $games = \App\Models\GameManage::whereIn('id', $gameIds)->pluck('name', 'id');

    $result = [];

    foreach ($gameClicks as $gameId => $gc) {
        $gs          = $gameSales->get($gameId);
        $totalClicks = (int) $gc->total_clicks;
        $conversions = (int) ($gs->conversions ?? 0);

        // Build subs
        $subs = [];
        $gameSubClicks = $subClicks->where('game_id', $gameId);
        $gameSubSales  = $subSales->where('game_id', $gameId)->keyBy(fn($r) => $r->sub1 . '|' . $r->sub2);

        foreach ($gameSubClicks as $sc) {
            $key = $sc->sub1 . '|' . $sc->sub2;
            $ss  = $gameSubSales->get($key);
            $sc_clicks = (int) $sc->total_clicks;
            $sc_conv   = (int) ($ss->conversions ?? 0);

            $label = collect([$sc->sub1, $sc->sub2])->filter()->join(' / ');
            if (!$label) continue; // skip rows with no subs

            $subs[] = [
                'sub1'        => $sc->sub1,
                'sub2'        => $sc->sub2,
                'label'       => $label,
                'clicks'      => $sc_clicks,
                'unique'      => (int) $sc->unique_clicks,
                'conversions' => $sc_conv,
                'profit'      => (float) ($ss->profit ?? 0),
                'cr'          => $sc_clicks > 0 ? round(($sc_conv / $sc_clicks) * 100, 2) : 0,
            ];
        }

        $result[] = [
            'game_id'     => $gameId,
            'game_name'   => $games->get($gameId, 'Unknown'),
            'clicks'      => $totalClicks,
            'unique'      => (int) $gc->unique_clicks,
            'conversions' => $conversions,
            'profit'      => (float) ($gs->profit ?? 0),
            'cr'          => $totalClicks > 0 ? round(($conversions / $totalClicks) * 100, 2) : 0,
            'subs'        => $subs,
        ];
    }

    return $result;
}
    /** Device breakdown — last 7 days */
    private function deviceBreakdown(int $uid): array
    {
        return AffiliateClick::where('affiliate_id', $uid)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->whereNotNull('device_type')
            ->select('device_type', DB::raw('COUNT(*) as count'))
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['label' => $r->device_type, 'value' => (int) $r->count])
            ->toArray();
    }

    /** OS / browser breakdown — last 7 days (using browser column as OS proxy) */
    private function osBreakdown(int $uid): array
    {
        return AffiliateClick::where('affiliate_id', $uid)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->whereNotNull('browser')
            ->select('browser', DB::raw('COUNT(*) as count'))
            ->groupBy('browser')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['label' => $r->browser, 'value' => (int) $r->count])
            ->toArray();
    }

    /** Best day / week / month + daily averages */
    private function performanceInsights(int $uid): array
    {
        // Best day
        $bestDay = AffiliateSale::where('affiliate_id', $uid)
            ->select(
                DB::raw('DATE(purchased_at) as period'),
                DB::raw('SUM(commission_amount) as profit'),
                DB::raw('COUNT(*) as conversions')
            )
            ->groupBy('period')
            ->orderByDesc('profit')
            ->first();

        // Best week
        $bestWeek = AffiliateSale::where('affiliate_id', $uid)
            ->select(
                DB::raw('YEARWEEK(purchased_at, 1) as period'),
                DB::raw('SUM(commission_amount) as profit'),
                DB::raw('COUNT(*) as conversions')
            )
            ->groupBy('period')
            ->orderByDesc('profit')
            ->first();

        // Best month
        $bestMonth = AffiliateSale::where('affiliate_id', $uid)
            ->select(
                DB::raw('DATE_FORMAT(purchased_at, "%Y-%m") as period'),
                DB::raw('SUM(commission_amount) as profit'),
                DB::raw('COUNT(*) as conversions')
            )
            ->groupBy('period')
            ->orderByDesc('profit')
            ->first();

        // Format best week label: YYYYWW → YYYY-WXX
        $weekLabel = null;
        if ($bestWeek) {
            $yw = (string) $bestWeek->period;
            $weekLabel = substr($yw, 0, 4) . '-W' . substr($yw, 4, 2);
        }

        // Format best month label
        $monthLabel = null;
        if ($bestMonth) {
            $monthLabel = Carbon::createFromFormat('Y-m', $bestMonth->period)->format('F Y');
        }

        // Daily averages (last 90 days)
        $daysWithClicks = AffiliateClick::where('affiliate_id', $uid)
            ->where('created_at', '>=', Carbon::now()->subDays(90))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->get();

        $daysWithSales = AffiliateSale::where('affiliate_id', $uid)
            ->where('purchased_at', '>=', Carbon::now()->subDays(90))
            ->selectRaw('DATE(purchased_at) as d, COUNT(*) as conversions, SUM(commission_amount) as profit')
            ->groupBy('d')
            ->get();

        $dayCount    = max($daysWithClicks->count(), 1);
        $avgClicks   = round($daysWithClicks->sum('c') / $dayCount, 1);
        $avgConv     = round($daysWithSales->sum('conversions') / $dayCount, 1);
        $avgProfit   = round($daysWithSales->sum('profit') / $dayCount, 2);

        return [
            'best_day'   => $bestDay ? [
                'period'      => (string) $bestDay->period,
                'profit'      => (float) $bestDay->profit,
                'conversions' => (int)   $bestDay->conversions,
            ] : null,
            'best_week'  => $bestWeek ? [
                'period'      => $weekLabel,
                'profit'      => (float) $bestWeek->profit,
                'conversions' => (int)   $bestWeek->conversions,
            ] : null,
            'best_month' => $bestMonth ? [
                'period'      => $monthLabel,
                'profit'      => (float) $bestMonth->profit,
                'conversions' => (int)   $bestMonth->conversions,
            ] : null,
            'daily_averages' => [
                'clicks'      => $avgClicks,
                'conversions' => $avgConv,
                'profit'      => $avgProfit,
            ],
        ];
    }

    /** Chart data — grouped by daily | weekly | monthly */
    private function chartData(int $uid, string $period, int $range): array
    {
        $start = Carbon::now()->subDays($range)->startOfDay();

        switch ($period) {
            case 'weekly':
                $clicksRaw = AffiliateClick::where('affiliate_id', $uid)
                    ->where('created_at', '>=', $start)
                    ->selectRaw('YEARWEEK(created_at, 1) as period, COUNT(*) as clicks, SUM(is_unique) as unique_clicks')
                    ->groupBy('period')->orderBy('period')->get();

                $salesRaw = AffiliateSale::where('affiliate_id', $uid)
                    ->where('purchased_at', '>=', $start)
                    ->selectRaw('YEARWEEK(purchased_at, 1) as period, COUNT(*) as conversions, SUM(commission_amount) as profit')
                    ->groupBy('period')->orderBy('period')->get()->keyBy('period');

                return $clicksRaw->map(function ($r) use ($salesRaw) {
                    $s    = $salesRaw->get($r->period);
                    $yw   = (string) $r->period;
                    $conv = (int) ($s->conversions ?? 0);
                    $cl   = (int) $r->clicks;
                    return [
                        'label'       => substr($yw, 0, 4) . '-W' . substr($yw, 4, 2),
                        'clicks'      => $cl,
                        'unique'      => (int) $r->unique_clicks,
                        'conversions' => $conv,
                        'profit'      => (float) ($s->profit ?? 0),
                        'cr'          => $cl > 0 ? round(($conv / $cl) * 100, 2) : 0,
                    ];
                })->values()->toArray();

            case 'monthly':
                $clicksRaw = AffiliateClick::where('affiliate_id', $uid)
                    ->where('created_at', '>=', Carbon::now()->subMonths(12))
                    ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, COUNT(*) as clicks, SUM(is_unique) as unique_clicks')
                    ->groupBy('period')->orderBy('period')->get();

                $salesRaw = AffiliateSale::where('affiliate_id', $uid)
                    ->where('purchased_at', '>=', Carbon::now()->subMonths(12))
                    ->selectRaw('DATE_FORMAT(purchased_at, "%Y-%m") as period, COUNT(*) as conversions, SUM(commission_amount) as profit')
                    ->groupBy('period')->orderBy('period')->get()->keyBy('period');

                return $clicksRaw->map(function ($r) use ($salesRaw) {
                    $s    = $salesRaw->get($r->period);
                    $conv = (int) ($s->conversions ?? 0);
                    $cl   = (int) $r->clicks;
                    return [
                        'label'       => Carbon::createFromFormat('Y-m', $r->period)->format('M Y'),
                        'clicks'      => $cl,
                        'unique'      => (int) $r->unique_clicks,
                        'conversions' => $conv,
                        'profit'      => (float) ($s->profit ?? 0),
                        'cr'          => $cl > 0 ? round(($conv / $cl) * 100, 2) : 0,
                    ];
                })->values()->toArray();

            default: // daily
                $clicksRaw = AffiliateClick::where('affiliate_id', $uid)
                    ->where('created_at', '>=', $start)
                    ->selectRaw('DATE(created_at) as period, COUNT(*) as clicks, SUM(is_unique) as unique_clicks')
                    ->groupBy('period')->orderBy('period')->get();

                $salesRaw = AffiliateSale::where('affiliate_id', $uid)
                    ->where('purchased_at', '>=', $start)
                    ->selectRaw('DATE(purchased_at) as period, COUNT(*) as conversions, SUM(commission_amount) as profit')
                    ->groupBy('period')->orderBy('period')->get()->keyBy('period');

                return $clicksRaw->map(function ($r) use ($salesRaw) {
                    $s    = $salesRaw->get($r->period);
                    $conv = (int) ($s->conversions ?? 0);
                    $cl   = (int) $r->clicks;
                    return [
                        'label'       => $r->period,
                        'clicks'      => $cl,
                        'unique'      => (int) $r->unique_clicks,
                        'conversions' => $conv,
                        'profit'      => (float) ($s->profit ?? 0),
                        'cr'          => $cl > 0 ? round(($conv / $cl) * 100, 2) : 0,
                    ];
                })->values()->toArray();
        }
    }
}