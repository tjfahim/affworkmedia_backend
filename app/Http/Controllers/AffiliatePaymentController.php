<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AffiliatePaymentController extends Controller
{
        public function getAllPayments(Request $request)
    {
        try {
            $query = AffiliatePayment::with('affiliate');
            
            // Filter by affiliate user ID
            if ($request->has('aff_user_id')) {
                $query->where('aff_user_id', $request->aff_user_id);
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            
            // Search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%")
                      ->orWhere('transaction_id', 'like', "%{$search}%");
                });
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $payments = $query->paginate($perPage);
            
            // Add statistics
            $statistics = [
                'total_payments' => AffiliatePayment::count(),
                'total_amount' => AffiliatePayment::sum('price'),
                'pending_count' => AffiliatePayment::where('status', 'pending')->count(),
                'pending_amount' => AffiliatePayment::where('status', 'pending')->sum('price'),
                'completed_count' => AffiliatePayment::where('status', 'completed')->count(),
                'completed_amount' => AffiliatePayment::where('status', 'completed')->sum('price'),
                'failed_count' => AffiliatePayment::where('status', 'failed')->count(),
                'cancelled_count' => AffiliatePayment::where('status', 'cancelled')->count(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $payments,
                'statistics' => $statistics,
                'message' => 'Payments retrieved successfully'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single payment details
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPayment($id)
    {
        try {
            $payment = AffiliatePayment::with('affiliate')->find($id);
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Payment retrieved successfully'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment from affiliate balance (Single function for full/partial payment)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPayment(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'aff_user_id' => 'required|exists:users,id',
                'amount' => 'nullable|numeric|min:0.01', // Optional: if not provided, will use full balance
                'title' => 'nullable|string|max:255',
                'pay_method' => 'required|string|max:50',
                'description' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Get affiliate user
            $affiliate = User::find($request->aff_user_id);
            
            // Check if user has affiliate role
            if (!$affiliate->hasRole('affiliate')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an affiliate'
                ], 400);
            }
            
            // Get current balance
            $currentBalance = $affiliate->balance ?? 0;
            
            // Check if balance is available
            if ($currentBalance <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Affiliate has no balance to withdraw'
                ], 400);
            }
            
            // Determine payment amount
            $paymentAmount = $request->has('amount') ? $request->amount : $currentBalance;
            
            // Validate payment amount doesn't exceed balance
            if ($paymentAmount > $currentBalance) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment amount (\${$paymentAmount}) exceeds available balance (\${$currentBalance})"
                ], 400);
            }
            
            // Calculate remaining balance
            $remainingBalance = $currentBalance - $paymentAmount;
            
            // Create payment record
            $payment = AffiliatePayment::create([
                'aff_user_id' => $request->aff_user_id,
                'title' => $request->title ?? ($paymentAmount == $currentBalance ? 'Full Balance Withdrawal' : 'Partial Balance Withdrawal'),
                'email' => $affiliate->email,
                'price' => $paymentAmount,
                'pay_method' => $request->pay_method,
                'description' => $request->description ?? ($paymentAmount == $currentBalance 
                    ? 'Withdrawal of full affiliate commission balance' 
                    : "Withdrawal of \${$paymentAmount} from affiliate commission balance"),
                'status' => 'pending',
                'notes' => $request->notes,
            ]);
            
            // Update affiliate balance based on payment type
            if ($paymentAmount == $currentBalance) {
                // Full payment - balance becomes 0
                $affiliate->balance = 0;
                $balanceMessage = "Full balance withdrawn. New balance: \$0";
            } else {
                // Partial payment - deduct amount from balance
                $affiliate->balance = $remainingBalance;
                $balanceMessage = "Partial withdrawal of \${$paymentAmount}. Remaining balance: \${$remainingBalance}";
            }
            
            $affiliate->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment->load('affiliate'),
                    'payment_amount' => $paymentAmount,
                    'previous_balance' => $currentBalance,
                    'current_balance' => $affiliate->balance,
                    'payment_type' => $paymentAmount == $currentBalance ? 'full' : 'partial',
                ],
                'message' => $balanceMessage
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit/Update payment
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function editPayment(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $payment = AffiliatePayment::find($id);
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'email' => 'sometimes|email|max:255',
                'price' => 'sometimes|numeric|min:0',
                'pay_method' => 'sometimes|string|max:50',
                'description' => 'nullable|string',
                'status' => 'sometimes|in:pending,completed,failed,cancelled',
                'transaction_id' => 'nullable|string|max:255|unique:affiliate_payments,transaction_id,' . $id,
                'notes' => 'nullable|string',
                'paid_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Store old price for balance adjustment if needed
            $oldPrice = $payment->price;
            $newPrice = $request->has('price') ? $request->price : $oldPrice;
            
            // If status is being changed to completed and no paid_at, set it
            if ($request->has('status') && $request->status === 'completed' && !$request->has('paid_at')) {
                $request->merge(['paid_at' => now()]);
            }
            
            // If price changed and payment is not completed, adjust affiliate balance
            if ($oldPrice != $newPrice && $payment->status !== 'completed') {
                $affiliate = User::find($payment->aff_user_id);
                $balanceDiff = $oldPrice - $newPrice;
                $affiliate->balance += $balanceDiff;
                $affiliate->save();
            }
            
            $payment->update($request->all());
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $payment->load('affiliate'),
                'message' => 'Payment updated successfully'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all affiliate users with their balance
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAffiliatesWithBalance(Request $request)
{
    try {
        $query = User::role('affiliate')
                    ->where('status', 'active');
        
        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Only get affiliates with balance > 0
        if ($request->has('has_balance') && $request->has_balance) {
            $query->where('balance', '>', 0);
        }
        
        // Filter by minimum balance
        if ($request->has('min_balance')) {
            $minBalance = (float)$request->min_balance;
            $query->where('balance', '>=', $minBalance);
        }
        
        $affiliates = $query->paginate($request->get('per_page', 15));
        
        // Format the response
        $affiliates->getCollection()->transform(function($affiliate) {
            return [
                'id' => $affiliate->id,
                'name' => $affiliate->first_name . ' ' . $affiliate->last_name,
                'first_name' => $affiliate->first_name,
                'last_name' => $affiliate->last_name,
                'email' => $affiliate->email,
                'balance' => $affiliate->balance ?? 0,
                'formatted_balance' => '$' . number_format($affiliate->balance ?? 0, 2),
                'status' => $affiliate->status,
                'company' => $affiliate->company,
                'pay_method' => $affiliate->pay_method,
                'paypal_email' => $affiliate->paypal,
                'payoneer_email' => $affiliate->payoneer,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $affiliates,
            'message' => 'Affiliates retrieved successfully'
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve affiliates',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Delete payment
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePayment($id)
    {
        DB::beginTransaction();
        
        try {
            $payment = AffiliatePayment::find($id);
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }
            
            // Prevent deletion of completed payments
            if ($payment->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete completed payments'
                ], 400);
            }
            
            // Restore balance to affiliate if payment is pending
            if ($payment->status === 'pending') {
                $affiliate = User::find($payment->aff_user_id);
                $affiliate->balance += $payment->price;
                $affiliate->save();
            }
            
            $payment->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully and balance restored'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,completed,failed,cancelled',
                'transaction_id' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $payment = AffiliatePayment::find($id);
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }
            
            $oldStatus = $payment->status;
            $newStatus = $request->status;
            
            // Handle balance restoration if payment is cancelled or failed
            if (in_array($newStatus, ['failed', 'cancelled']) && $oldStatus === 'pending') {
                $affiliate = User::find($payment->aff_user_id);
                $affiliate->balance += $payment->price;
                $affiliate->save();
            }
            
            // Handle balance deduction if payment is completed from pending
            if ($newStatus === 'completed' && $oldStatus === 'pending') {
                // Balance already deducted when payment was created, so no action needed
                // Just mark as completed
            }
            
            $payment->status = $newStatus;
            
            if ($newStatus === 'completed' && !$payment->paid_at) {
                $payment->paid_at = now();
            }
            
            if ($request->has('transaction_id')) {
                $payment->transaction_id = $request->transaction_id;
            }
            
            if ($request->has('notes')) {
                $payment->notes = $request->notes;
            }
            
            $payment->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => "Payment status updated from {$oldStatus} to {$newStatus}"
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments by affiliate user
     *
     * @param int $userId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAffiliatePayments($userId, Request $request)
    {
        try {
            $affiliate = User::find($userId);
            
            if (!$affiliate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Affiliate not found'
                ], 404);
            }
            
            $query = AffiliatePayment::where('aff_user_id', $userId);
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Date range filter
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            
            $payments = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));
            
            $summary = [
                'total_withdrawn' => $query->where('status', 'completed')->sum('price'),
                'pending_withdrawals' => $query->where('status', 'pending')->sum('price'),
                'total_transactions' => $query->count(),
                'completed_transactions' => $query->where('status', 'completed')->count(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $payments,
                'summary' => $summary,
                'affiliate' => [
                    'id' => $affiliate->id,
                    'name' => $affiliate->first_name . ' ' . $affiliate->last_name,
                    'email' => $affiliate->email,
                    'current_balance' => $affiliate->balance ?? 0,
                    'formatted_balance' => '$' . number_format($affiliate->balance ?? 0, 2),
                ],
                'message' => 'Affiliate payments retrieved successfully'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve affiliate payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get balance summary for all affiliates
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBalanceSummary()
    {
        try {
            $totalBalance = User::role('affiliate')->sum('balance');
            $affiliatesWithBalance = User::role('affiliate')->where('balance', '>', 0)->count();
            $averageBalance = $affiliatesWithBalance > 0 ? $totalBalance / $affiliatesWithBalance : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_balance' => $totalBalance,
                    'formatted_total_balance' => '$' . number_format($totalBalance, 2),
                    'affiliates_with_balance' => $affiliatesWithBalance,
                    'average_balance' => $averageBalance,
                    'formatted_average_balance' => '$' . number_format($averageBalance, 2),
                ],
                'message' => 'Balance summary retrieved successfully'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve balance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function generateInvoice($id)
{
    try {
        $payment = AffiliatePayment::with('affiliate')->find($id);
        
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }
        
        // Create HTML invoice
        $html = view('invoices.payment', compact('payment'))->render();
        
        // Generate PDF (you'll need to install a package like barryvdh/laravel-dompdf)
        // For now, return JSON with invoice data
        return response()->json([
            'success' => true,
            'data' => [
                'invoice_number' => 'INV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'payment' => $payment,
                'html' => $html
            ],
            'message' => 'Invoice generated successfully'
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate invoice',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
