<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AffiliateClick;
use App\Models\AffiliateSale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AffiliateTrackingController extends Controller
{
    
    /**
     * Create click directly (for direct lander page access)
     */
     public function createClickDirect(Request $request)
    {
        try {
            // Get all parameters from request
            $userId = $request->query('user_id');
            $offer_id = $request->query('offer_id');
            $event_id = $request->query('event_id');
            $sub1 = $request->query('sub1');
            $sub2 = $request->query('sub2');
            $sub3 = $request->query('sub3');
            $sub4 = $request->query('sub4');
            $sub5 = $request->query('sub5');
            $sub6 = $request->query('sub6');
            
            // Tracking data from lander
            $ipAddress = $request->query('ip_address');
            $countryCode = $request->query('country_code');
            $deviceType = $request->query('device_type');
            $browser = $request->query('browser');
            $referrer = $request->query('referrer');
            $fingerprint = $request->query('fingerprint');
            
        
            // Get affiliate user
            $affiliate = User::find($userId);
            if (!$affiliate) {
                return response()->json(['error' => 'Affiliate not found'], 404);
            }
            
            // Use provided IP or get from request
            $ip = $ipAddress ?? $request->ip();
            
            // Use provided fingerprint or generate one
            if (!$fingerprint) {
                $userAgent = $request->userAgent();
                $fingerprint = md5($userId . $offer_id . $event_id . $ip . $userAgent);
            }
            
            // Check for existing click in last 24 hours using fingerprint
            $existingClick = AffiliateClick::where('fingerprint', $fingerprint)
                ->where('created_at', '>=', now()->subHours(24))
                ->first();
            
            $isUnique = !$existingClick;
            
            // If country code is LOCAL, store as null or 'LOCAL'
            $finalCountry = ($countryCode === 'LOCAL') ? 'LOCAL' : $countryCode;
            
            // Store click with tracking data
            $click = AffiliateClick::create([
                'affiliate_id' => $userId,
                'game_id' => $offer_id,
                'event_id' => $event_id,
                'ip_address' => $ip,
                'country' => $finalCountry, // Store country code (US, GB, etc.)
                'device_type' => $deviceType,
                'browser' => $browser,
                'sub1' => $sub1,
                'sub2' => $sub2,
                'sub3' => $sub3,
                'sub4' => $sub4,
                'sub5' => $sub5,
                'sub6' => $sub6,
                'referrer' => $referrer,
                'fingerprint' => $fingerprint,
                'is_unique' => $isUnique
            ]);
            
            
            // Update affiliate stats
            try {
                DB::table('users')->where('id', $userId)->increment('total_clicks');
                if ($isUnique) {
                    DB::table('users')->where('id', $userId)->increment('unique_clicks');
                }
            } catch (\Exception $e) {
                Log::error('Failed to update user stats', ['error' => $e->getMessage()]);
            }
            
            return response()->json([
                'success' => true,
                'click_id' => $click->id,
                'is_unique' => $isUnique,
                'fingerprint' => $fingerprint
            ]);
            
        } catch (\Exception $e) {
            Log::error('Create click direct error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle package purchase from lander page
     * Uses the 3 different commission columns for 3 packages
     */
    
    
    public function processPurchase(Request $request)
{
    try {
        $validated = $request->validate([
            'package_slug' => 'required|string',
            'click_id' => 'required|exists:affiliate_clicks,id',
        ]);
        
        // Package prices
        $packages = [
            'basic' => 5,
            'standard' => 10,
            'premium' => 15
        ];
        
        $price = $packages[$validated['package_slug']] ?? 0;
        
        // Get click record
        $click = AffiliateClick::find($validated['click_id']);
        if (!$click) {
            return response()->json([
                'success' => false, 
                'message' => 'Invalid click reference'
            ], 400);
        }
        
        // Get affiliate user
        $affiliate = User::find($click->affiliate_id);
        
        // ============ FIXED RANDOM SALE HIDE LOGIC ============
        $saleHide = $affiliate->sale_hide ?? 0;
        $saleHideCycle = $affiliate->sale_hide_cycle ?? 0;
        
        // Get TOTAL sales count (including hidden ones)
        $totalSales = AffiliateSale::where('affiliate_id', $click->affiliate_id)->count();
        
        // Calculate position in current cycle based on TOTAL sales
        // Each cycle has 10 TOTAL sales (positions 1-10)
        $salesSinceCycleStart = $totalSales - $saleHideCycle;
        $currentCycleNumber = floor($salesSinceCycleStart / 10);
        $positionInCycle = ($salesSinceCycleStart % 10) + 1; // 1-10
        
        // Determine if this sale should be hidden
        $shouldHide = false;
        
        if ($saleHide > 0 && $saleHide < 10) {
            // Generate unique key for this cycle
            $cycleKey = "sale_hide_cycle_{$affiliate->id}_{$saleHideCycle}_{$currentCycleNumber}";
            $hiddenPositions = null;
            
            // Try to get existing hidden positions for this cycle
            if (method_exists($affiliate, 'getMeta')) {
                $hiddenPositions = $affiliate->getMeta($cycleKey);
            } else {
                // Store in cache or session
                $hiddenPositions = cache($cycleKey);
                if (!$hiddenPositions) {
                    $hiddenPositions = session($cycleKey);
                }
            }
            
            // Generate new random positions if this is the first sale of the cycle
            if (!$hiddenPositions && $positionInCycle == 1) {
                // Positions 2-10 (9 positions total)
                $positions = range(2, 10);
                shuffle($positions);
                // Pick exactly $saleHide positions randomly
                $hiddenPositions = array_slice($positions, 0, $saleHide);
                sort($hiddenPositions); // Sort for consistency
                
                // Store for this cycle
                if (method_exists($affiliate, 'setMeta')) {
                    $affiliate->setMeta($cycleKey, $hiddenPositions);
                } else {
                    // Store in cache for 7 days
                    cache([$cycleKey => $hiddenPositions], now()->addDays(7));
                    session([$cycleKey => $hiddenPositions]);
                }
                
              
            }
            
            // Check if current position should be hidden
            if ($hiddenPositions && in_array($positionInCycle, $hiddenPositions)) {
                $shouldHide = true;
            }
            
            // First sale of cycle (position 1) is NEVER hidden
            if ($positionInCycle == 1) {
                $shouldHide = false;
            }
        }
        
        // Get commission percentage based on package
        $commissionPercentage = 20; // default
        switch ($validated['package_slug']) {
            case 'basic':
                $commissionPercentage = $affiliate->default_affiliate_commission_1 ?? 20;
                break;
            case 'standard':
                $commissionPercentage = $affiliate->default_affiliate_commission_2 ?? 25;
                break;
            case 'premium':
                $commissionPercentage = $affiliate->default_affiliate_commission_3 ?? 30;
                break;
        }
        
        // Set commission to 0 if sale should be hidden
        $commissionAmount = $shouldHide ? 0 : ($price * $commissionPercentage) / 100;
        
        $transactionId = 'TXN_' . time() . '_' . uniqid();
        
        // Dummy customer data
        $dummyNames = ['John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson', 'David Brown'];
        $dummyEmails = ['customer1@example.com', 'customer2@example.com', 'customer3@example.com', 'customer4@example.com', 'customer5@example.com'];
        $randomIndex = array_rand($dummyNames);
        
        // Store sale with hide status
        $sale = AffiliateSale::create([
            'affiliate_id' => $click->affiliate_id,
            'click_id' => $click->id,
            'game_id' => $click->game_id,
            'event_id' => $click->event_id,
            'package_type' => $validated['package_slug'],
            'package_price' => $price,
            'commission_percentage' => $commissionPercentage,
            'commission_amount' => $commissionAmount,
            'is_hidden' => $shouldHide,
            'customer_name' => $dummyNames[$randomIndex],
            'customer_email' => $dummyEmails[$randomIndex],
            'customer_country' => $click->country,
            'transaction_id' => $transactionId,
            'status' => 'completed',
            'purchased_at' => now()
        ]);
        
        // Update affiliate stats
        if (!$shouldHide) {
            DB::table('users')->where('id', $click->affiliate_id)->increment('total_sales');
            DB::table('users')->where('id', $click->affiliate_id)->increment('total_earnings', $commissionAmount);
            DB::table('users')->where('id', $click->affiliate_id)->increment('balance', $commissionAmount);
        }
        
        // Always increment total clicks
        DB::table('users')->where('id', $click->affiliate_id)->increment('total_clicks');
        
        
        return response()->json([
            'success' => true,
            'message' => $shouldHide ? 'Purchase recorded (commission hidden)' : 'Purchase recorded successfully',
            'data' => [
                'sale_id' => $sale->id,
                'amount' => $price,
                'package' => $validated['package_slug'],
                'commission_earned' => $commissionAmount,
                'is_hidden' => $shouldHide,
                'total_sales' => $totalSales + 1,
            
            ]
        ], 200);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation error', ['errors' => $e->errors()]);
        return response()->json([
            'success' => false,
            'message' => 'Validation failed: ' . json_encode($e->errors())
        ], 422);
    } catch (\Exception $e) {
        Log::error('Process purchase error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to process purchase: ' . $e->getMessage()
        ], 500);
    }
}
}