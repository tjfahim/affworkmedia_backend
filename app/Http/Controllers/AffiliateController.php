<?php
// app/Http/Controllers/AffiliateController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AffiliateController extends Controller
{
    /**
     * Get all affiliate users with their details
     */
    public function index(Request $request)
    {
        // Get users with affiliate role
        $affiliates = User::role('affiliate')
            ->with('roles')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'affiliates' => $affiliates
        ]);
    }

    /**
     * Get all affiliates for dropdown/select
     */
    public function getAllAffiliates(Request $request)
    {
        $affiliates = User::role('affiliate')
            ->where('status', 'active')
            ->select('id', 'first_name', 'last_name', 'email')
            ->get();

        return response()->json([
            'success' => true,
            'affiliates' => $affiliates
        ]);
    }

    /**
     * Create a new affiliate user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'address' => 'nullable|string',
            'balance' => 'nullable|numeric|min:0',
            'pay_method' => 'nullable|string|max:255',
            'account_email' => 'nullable|email',
            'skype' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|url',
            'promotion_description' => 'nullable|string',
            'payoneer' => 'nullable|string|max:255',
            'paypal' => 'nullable|email',
            'binance' => 'nullable|string|max:255',
            'bank_details' => 'nullable|string',
            'other_payment_method_description' => 'nullable|string',
            'default_affiliate_commission_1' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_2' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_3' => 'nullable|numeric|min:0|max:100',
            'sale_hide' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Get settings for default affiliate commissions
        $settings = Setting::getSettings();

        // Create user
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'balance' => $request->balance ?? 0,
            'pay_method' => $request->pay_method,
            'account_email' => $request->account_email,
            'skype' => $request->skype,
            'company' => $request->company,
            'website' => $request->website,
            'promotion_description' => $request->promotion_description,
            'payoneer' => $request->payoneer,
            'paypal' => $request->paypal,
            'binance' => $request->binance,
            'bank_details' => $request->bank_details,
            'other_payment_method_description' => $request->other_payment_method_description,
            'default_affiliate_commission_1' => $request->default_affiliate_commission_1 ?? $settings->default_affiliate_commission_1 ?? 0,
            'default_affiliate_commission_2' => $request->default_affiliate_commission_2 ?? $settings->default_affiliate_commission_2 ?? 0,
            'default_affiliate_commission_3' => $request->default_affiliate_commission_3 ?? $settings->default_affiliate_commission_3 ?? 0,
            'sale_hide' => $request->sale_hide ?? 3,
            'status' => $request->status ?? 'active',
            // Default statuses for payment methods
            'edit_paypal_mail_status' => $request->edit_paypal_mail_status ?? 'deactive',
            'edit_payoneer_mail_status' => $request->edit_payoneer_mail_status ?? 'deactive',
            'edit_bank_details_status' => $request->edit_bank_details_status ?? 'deactive',
            'edit_binance_mail_status' => $request->edit_binance_mail_status ?? 'deactive',
            'edit_other_payment_method_description_status' => $request->edit_other_payment_method_description_status ?? 'deactive',
        ]);

        // Assign affiliate role
        $user->assignRole('affiliate');

        return response()->json([
            'success' => true,
            'message' => 'Affiliate created successfully',
            'affiliate' => $user->load('roles')
        ], 201);
    }

    /**
     * Get single affiliate details
     */
    public function show(Request $request, $id)
    {
        $affiliate = User::role('affiliate')->with('roles')->find($id);
        
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'affiliate' => $affiliate
        ]);
    }

    /**
     * Update affiliate
     */
 public function update(Request $request, $id)
{
    $affiliate = User::role('affiliate')->find($id);
    
    if (!$affiliate) {
        return response()->json([
            'success' => false,
            'message' => 'Affiliate not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,' . $id,
        'password' => 'nullable|string|min:6', // Changed from 'sometimes' to 'nullable'
        'address' => 'nullable|string',
        'balance' => 'nullable|numeric|min:0',
        'pay_method' => 'nullable|string|max:255',
        'account_email' => 'nullable|email',
        'skype' => 'nullable|string|max:255',
        'company' => 'nullable|string|max:255',
        'website' => 'nullable|url',
        'promotion_description' => 'nullable|string',
        'payoneer' => 'nullable|string|max:255',
        'paypal' => 'nullable|email',
        'binance' => 'nullable|string|max:255',
        'bank_details' => 'nullable|string',
        'other_payment_method_description' => 'nullable|string',
        'default_affiliate_commission_1' => 'nullable|numeric|min:0|max:100',
        'default_affiliate_commission_2' => 'nullable|numeric|min:0|max:100',
        'default_affiliate_commission_3' => 'nullable|numeric|min:0|max:100',
        'sale_hide' => 'nullable|numeric|min:0|max:100',
        'status' => 'sometimes|in:active,inactive,suspended',
        'edit_paypal_mail_status' => 'sometimes|in:active,deactive,requested',
        'edit_payoneer_mail_status' => 'sometimes|in:active,deactive,requested',
        'edit_bank_details_status' => 'sometimes|in:active,deactive,requested',
        'edit_binance_mail_status' => 'sometimes|in:active,deactive,requested',
        'edit_other_payment_method_description_status' => 'sometimes|in:active,deactive,requested',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // Update affiliate data
    $updateData = $request->only([
        'first_name',
        'last_name',
        'email',
        'address',
        'balance',
        'pay_method',
        'account_email',
        'skype',
        'company',
        'website',
        'promotion_description',
        'payoneer',
        'paypal',
        'binance',
        'bank_details',
        'other_payment_method_description',
        'default_affiliate_commission_1',
        'default_affiliate_commission_2',
        'default_affiliate_commission_3',
        'sale_hide',
        'status',
        'edit_paypal_mail_status',
        'edit_payoneer_mail_status',
        'edit_bank_details_status',
        'edit_binance_mail_status',
        'edit_other_payment_method_description_status',
    ]);

    // Remove null values if not provided
    $updateData = array_filter($updateData, function($value) {
        return $value !== null;
    });

    // Only update password if it's provided and not empty
    if ($request->has('password') && !empty($request->password)) {
        $updateData['password'] = Hash::make($request->password);
    }

    $affiliate->update($updateData);

    return response()->json([
        'success' => true,
        'message' => 'Affiliate updated successfully',
        'affiliate' => $affiliate->load('roles')
    ]);
}
    /**
     * Delete affiliate
     */
    public function destroy(Request $request, $id)
    {
        $affiliate = User::role('affiliate')->find($id);
        
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate not found'
            ], 404);
        }

        $affiliate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Affiliate deleted successfully'
        ]);
    }

    /**
     * Update affiliate status only
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $affiliate = User::role('affiliate')->find($id);
        
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate not found'
            ], 404);
        }

        $affiliate->status = $request->status;
        $affiliate->save();

        return response()->json([
            'success' => true,
            'message' => 'Affiliate status updated successfully',
            'affiliate' => $affiliate
        ]);
    }

    /**
     * Update affiliate commission levels (1,2,3)
     */
    public function updateCommissionLevels(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'default_affiliate_commission_1' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_2' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_3' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $affiliate = User::role('affiliate')->find($id);
        
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate not found'
            ], 404);
        }

        $updateData = [];
        if ($request->has('default_affiliate_commission_1')) {
            $updateData['default_affiliate_commission_1'] = $request->default_affiliate_commission_1;
        }
        if ($request->has('default_affiliate_commission_2')) {
            $updateData['default_affiliate_commission_2'] = $request->default_affiliate_commission_2;
        }
        if ($request->has('default_affiliate_commission_3')) {
            $updateData['default_affiliate_commission_3'] = $request->default_affiliate_commission_3;
        }

        $affiliate->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Affiliate commission levels updated successfully',
            'affiliate' => $affiliate
        ]);
    }

    /**
     * Update affiliate payment status
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'edit_paypal_mail_status' => 'sometimes|in:active,deactive,requested',
            'edit_payoneer_mail_status' => 'sometimes|in:active,deactive,requested',
            'edit_bank_details_status' => 'sometimes|in:active,deactive,requested',
            'edit_binance_mail_status' => 'sometimes|in:active,deactive,requested',
            'edit_other_payment_method_description_status' => 'sometimes|in:active,deactive,requested',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $affiliate = User::role('affiliate')->find($id);
        
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate not found'
            ], 404);
        }

        $affiliate->update($request->only([
            'edit_paypal_mail_status',
            'edit_payoneer_mail_status',
            'edit_bank_details_status',
            'edit_binance_mail_status',
            'edit_other_payment_method_description_status',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Affiliate payment status updated successfully',
            'affiliate' => $affiliate
        ]);
    }

    /**
     * Get affiliate with their payment methods
     */
    public function getPaymentMethods($id)
    {
        $affiliate = User::role('affiliate')->find($id);
        
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'payment_methods' => $affiliate->getActivePaymentMethods(),
            'payment_statuses' => [
                'paypal' => $affiliate->edit_paypal_mail_status,
                'payoneer' => $affiliate->edit_payoneer_mail_status,
                'bank' => $affiliate->edit_bank_details_status,
                'binance' => $affiliate->edit_binance_mail_status,
                'other' => $affiliate->edit_other_payment_method_description_status,
            ]
        ]);
    }
}