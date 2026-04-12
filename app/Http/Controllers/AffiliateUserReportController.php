<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\AffiliateSale;
use App\Models\GameManage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AffiliateUserReportController extends Controller
{
   
    /**
     * Report 1: Games Performance Report with Sub Breakdown
     */
    public function gamesReport(Request $request)
    {
        try {
            $user = Auth::user();
            $uid = $user->id;
            
            $validated = $request->validate([
                'from_date' => 'nullable|date|date_format:Y-m-d',
                'to_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:from_date',
            ]);
            
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            
            // Build query for clicks
            $clicksQuery = AffiliateClick::where('affiliate_id', $uid)
                ->whereNotNull('game_id')
                ->select(
                    'game_id',
                    DB::raw('COUNT(*) as total_clicks'),
                    DB::raw('SUM(is_unique) as unique_clicks')
                );
            
            // Build query for sales - ONLY visible sales
            $salesQuery = AffiliateSale::where('affiliate_id', $uid)
                ->whereNotNull('game_id')
                ->where('is_hidden', false)
                ->select(
                    'game_id',
                    DB::raw('COUNT(*) as total_sales'),
                    DB::raw('SUM(commission_amount) as total_commission')
                );
            
            if ($fromDate) {
                $startDate = Carbon::parse($fromDate)->startOfDay();
                $clicksQuery->where('created_at', '>=', $startDate);
                $salesQuery->where('purchased_at', '>=', $startDate);
            }
            
            if ($toDate) {
                $endDate = Carbon::parse($toDate)->endOfDay();
                $clicksQuery->where('created_at', '<=', $endDate);
                $salesQuery->where('purchased_at', '<=', $endDate);
            }
            
            $gameClicks = $clicksQuery->groupBy('game_id')->get()->keyBy('game_id');
            $gameIds = $gameClicks->keys();
            $gameSales = $salesQuery->groupBy('game_id')->get()->keyBy('game_id');
            
            // Sub-level breakdown
            $subClicks = AffiliateClick::where('affiliate_id', $uid)
                ->whereIn('game_id', $gameIds)
                ->select(
                    'game_id', 'sub1', 'sub2',
                    DB::raw('COUNT(*) as total_clicks'),
                    DB::raw('SUM(is_unique) as unique_clicks')
                )
                ->groupBy('game_id', 'sub1', 'sub2');
            
            if ($fromDate) {
                $subClicks->where('created_at', '>=', $startDate);
            }
            if ($toDate) {
                $subClicks->where('created_at', '<=', $endDate);
            }
            $subClicks = $subClicks->get();
            
            $subSales = AffiliateSale::where('affiliate_sales.affiliate_id', $uid)
                ->where('affiliate_sales.is_hidden', false)
                ->join('affiliate_clicks', 'affiliate_sales.click_id', '=', 'affiliate_clicks.id')
                ->whereIn('affiliate_sales.game_id', $gameIds)
                ->select(
                    'affiliate_sales.game_id',
                    'affiliate_clicks.sub1',
                    'affiliate_clicks.sub2',
                    DB::raw('COUNT(*) as conversions'),
                    DB::raw('SUM(affiliate_sales.commission_amount) as profit')
                )
                ->groupBy('affiliate_sales.game_id', 'affiliate_clicks.sub1', 'affiliate_clicks.sub2');
            
            if ($fromDate) {
                $subSales->where('affiliate_sales.purchased_at', '>=', $startDate);
            }
            if ($toDate) {
                $subSales->where('affiliate_sales.purchased_at', '<=', $endDate);
            }
            $subSales = $subSales->get();
            
            $games = GameManage::whereIn('id', $gameIds)->pluck('name', 'id');
            
            $results = [];
            foreach ($gameClicks as $gameId => $gc) {
                $gs = $gameSales->get($gameId);
                $totalClicks = (int) ($gc->total_clicks ?? 0);
                $uniqueClicks = (int) ($gc->unique_clicks ?? 0);
                $totalSales = (int) ($gs->total_sales ?? 0);
                $totalCommission = (float) ($gs->total_commission ?? 0);
                $conversionRate = $totalClicks > 0 ? round(($totalSales / $totalClicks) * 100, 2) : 0;
                
                $subs = [];
                $gameSubClicks = $subClicks->where('game_id', $gameId);
                $gameSubSales = $subSales->where('game_id', $gameId)->keyBy(fn($r) => $r->sub1 . '|' . $r->sub2);
                
                foreach ($gameSubClicks as $sc) {
                    $key = $sc->sub1 . '|' . $sc->sub2;
                    $ss = $gameSubSales->get($key);
                    $sc_clicks = (int) $sc->total_clicks;
                    $sc_conv = (int) ($ss->conversions ?? 0);
                    $label = collect([$sc->sub1, $sc->sub2])->filter()->join(' / ');
                    if (!$label) continue;
                    
                    $subs[] = [
                        'sub1' => $sc->sub1,
                        'sub2' => $sc->sub2,
                        'label' => $label,
                        'clicks' => $sc_clicks,
                        'unique' => (int) $sc->unique_clicks,
                        'conversions' => $sc_conv,
                        'profit' => (float) ($ss->profit ?? 0),
                        'cr' => $sc_clicks > 0 ? round(($sc_conv / $sc_clicks) * 100, 2) : 0,
                    ];
                }
                
                $results[] = [
                    'game_id' => $gameId,
                    'game_name' => $games->get($gameId, 'Unknown Game'),
                    'total_clicks' => $totalClicks,
                    'unique_clicks' => $uniqueClicks,
                    'total_sales' => $totalSales,
                    'total_commission' => $totalCommission,
                    'conversion_rate' => $conversionRate,
                    'subs' => $subs,
                ];
            }
            
            usort($results, function($a, $b) {
                return $b['total_commission'] <=> $a['total_commission'];
            });
            
            $summary = [
                'total_clicks' => array_sum(array_column($results, 'total_clicks')),
                'total_unique_clicks' => array_sum(array_column($results, 'unique_clicks')),
                'total_sales' => array_sum(array_column($results, 'total_sales')),
                'total_commission' => array_sum(array_column($results, 'total_commission')),
                'average_conversion_rate' => count($results) > 0 
                    ? round(array_sum(array_column($results, 'conversion_rate')) / count($results), 2)
                    : 0,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => $summary,
                'filters' => ['from_date' => $fromDate, 'to_date' => $toDate],
                'total_records' => count($results),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate games report: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Report 2: Conversions/Transactions Report (Only visible sales) - Country removed
     */
  /**
 * Report 2: Conversions/Transactions Report (Only visible sales) - Country removed
 * GET /affiliate/reports/conversions
 */
public function conversionsReport(Request $request)
{
    try {
        $user = Auth::user();
        $uid = $user->id;
        
        $validated = $request->validate([
            'from_date' => 'nullable|date|date_format:Y-m-d',
            'to_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:from_date',
            'game_id' => 'nullable|integer|exists:game_manages,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $gameId = $request->input('game_id');
        $perPage = $request->input('per_page', 20);
        
        // Base query for counting ALL records (without pagination)
        $countQuery = AffiliateSale::where('affiliate_sales.affiliate_id', $uid)
            ->where('affiliate_sales.is_hidden', false)
            ->join('affiliate_clicks', 'affiliate_sales.click_id', '=', 'affiliate_clicks.id');
        
        // Apply date filters to count query
        if ($fromDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $countQuery->where('affiliate_sales.purchased_at', '>=', $startDate);
        }
        if ($toDate) {
            $endDate = Carbon::parse($toDate)->endOfDay();
            $countQuery->where('affiliate_sales.purchased_at', '<=', $endDate);
        }
        if ($gameId) {
            $countQuery->where('affiliate_sales.game_id', $gameId);
        }
        
        // Get TOTAL counts for summary (from ALL records, not just current page)
        $totalTransactions = $countQuery->count();
        $totalCommission = (float) $countQuery->sum('affiliate_sales.commission_amount');
        $averageCommission = $totalTransactions > 0 ? round($totalCommission / $totalTransactions, 2) : 0;
        
        // Build paginated query for data display
        $query = AffiliateSale::where('affiliate_sales.affiliate_id', $uid)
            ->where('affiliate_sales.is_hidden', false)
            ->join('affiliate_clicks', 'affiliate_sales.click_id', '=', 'affiliate_clicks.id')
            ->leftJoin('game_manages', 'affiliate_sales.game_id', '=', 'game_manages.id')
            ->select(
                'affiliate_sales.id',
                'affiliate_sales.purchased_at as transaction_date',
                'affiliate_sales.game_id',
                'game_manages.name as game_name',
                'affiliate_sales.package_type',
                'affiliate_sales.package_price',
                'affiliate_sales.commission_amount',
                'affiliate_sales.transaction_id',
                'affiliate_sales.status',
                'affiliate_clicks.device_type',
                'affiliate_clicks.browser',
                'affiliate_clicks.sub1',
                'affiliate_clicks.sub2',
                'affiliate_clicks.sub3',
                'affiliate_clicks.sub4',
                'affiliate_clicks.sub5',
                'affiliate_clicks.sub6'
            );
        
        // Apply same filters to paginated query
        if ($fromDate) {
            $query->where('affiliate_sales.purchased_at', '>=', $startDate);
        }
        if ($toDate) {
            $query->where('affiliate_sales.purchased_at', '<=', $endDate);
        }
        if ($gameId) {
            $query->where('affiliate_sales.game_id', $gameId);
        }
        
        // Order by transaction date descending
        $query->orderBy('affiliate_sales.purchased_at', 'desc');
        
        // Paginate results
        $transactions = $query->paginate($perPage);
        
        // Summary using TOTAL values (not paginated)
        $summary = [
            'total_transactions' => $totalTransactions,
            'total_commission' => $totalCommission,
            'average_commission' => $averageCommission,
        ];
        
        // Get unique games for filter options
        $filterOptions = [
            'games' => AffiliateSale::where('affiliate_id', $uid)
                ->where('is_hidden', false)
                ->whereNotNull('game_id')
                ->with('game')
                ->get()
                ->unique('game_id')
                ->map(function($sale) {
                    return [
                        'id' => $sale->game_id,
                        'name' => $sale->game ? $sale->game->name : 'Unknown'
                    ];
                })
                ->values()
        ];
        
        // Format data
        $formattedData = [];
        foreach ($transactions->items() as $transaction) {
            $formattedData[] = [
                'id' => $transaction->id,
                'transaction_date' => $transaction->transaction_date,
                'game_id' => $transaction->game_id,
                'game_name' => $transaction->game_name ?? 'Unknown',
                'package_type' => $transaction->package_type,
                'package_price' => (float) $transaction->package_price,
                'commission_amount' => (float) $transaction->commission_amount,
                'transaction_id' => $transaction->transaction_id,
                'status' => $transaction->status,
                'device_type' => $transaction->device_type ?? 'N/A',
                'browser' => $transaction->browser ?? 'N/A',
                'sub_parameters' => [
                    'sub1' => $transaction->sub1,
                    'sub2' => $transaction->sub2,
                    'sub3' => $transaction->sub3,
                    'sub4' => $transaction->sub4,
                    'sub5' => $transaction->sub5,
                    'sub6' => $transaction->sub6,
                ]
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'summary' => $summary,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'game_id' => $gameId,
            ],
            'filter_options' => $filterOptions,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate conversions report: ' . $e->getMessage(),
        ], 500);
    }
}
}