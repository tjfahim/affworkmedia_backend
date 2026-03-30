<?php
// app/Http/Controllers/AffiliateController.php

namespace App\Http\Controllers;

use App\Models\User;
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
            'aff_percent' => 'nullable|numeric|min:0|max:100',
            'sale_add' => 'nullable|integer|min:0|max:100',
            'auto_renew' => 'nullable|boolean',
            'sale_hide' => 'nullable|integer|min:0|max:100',
            'status' => 'sometimes|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Create user
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name . ' ' . $request->last_name,
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
            'aff_percent' => $request->aff_percent ?? 0,
            'sale_add' => $request->sale_add ?? 0,
            'auto_renew' => $request->auto_renew ?? false,
            'sale_hide' => $request->sale_hide ?? 0,
            'status' => $request->status ?? 'active'
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
            'password' => 'sometimes|string|min:6',
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
            'aff_percent' => 'nullable|numeric|min:0|max:100',
            'sale_add' => 'nullable|integer|min:0|max:100',
            'auto_renew' => 'nullable|boolean',
            'sale_hide' => 'nullable|integer|min:0|max:100',
            'status' => 'sometimes|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Update affiliate data
        if ($request->has('first_name')) {
            $affiliate->first_name = $request->first_name;
        }
        if ($request->has('last_name')) {
            $affiliate->last_name = $request->last_name;
        }
        if ($request->has('first_name') || $request->has('last_name')) {
            $affiliate->name = $affiliate->first_name . ' ' . $affiliate->last_name;
        }
        if ($request->has('email')) {
            $affiliate->email = $request->email;
        }
        if ($request->has('password') && !empty($request->password)) {
            $affiliate->password = Hash::make($request->password);
        }
        if ($request->has('address')) {
            $affiliate->address = $request->address;
        }
        if ($request->has('balance')) {
            $affiliate->balance = $request->balance;
        }
        if ($request->has('pay_method')) {
            $affiliate->pay_method = $request->pay_method;
        }
        if ($request->has('account_email')) {
            $affiliate->account_email = $request->account_email;
        }
        if ($request->has('skype')) {
            $affiliate->skype = $request->skype;
        }
        if ($request->has('company')) {
            $affiliate->company = $request->company;
        }
        if ($request->has('website')) {
            $affiliate->website = $request->website;
        }
        if ($request->has('promotion_description')) {
            $affiliate->promotion_description = $request->promotion_description;
        }
        if ($request->has('payoneer')) {
            $affiliate->payoneer = $request->payoneer;
        }
        if ($request->has('paypal')) {
            $affiliate->paypal = $request->paypal;
        }
        if ($request->has('aff_percent')) {
            $affiliate->aff_percent = $request->aff_percent;
        }
        if ($request->has('sale_add')) {
            $affiliate->sale_add = (int) $request->sale_add;
        }
        if ($request->has('auto_renew')) {
            $affiliate->auto_renew = $request->auto_renew;
        }
        if ($request->has('sale_hide')) {
            $affiliate->sale_hide = (int) $request->sale_hide;
        }
        if ($request->has('status')) {
            $affiliate->status = $request->status;
        }

        $affiliate->save();

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
     * Update affiliate commission rate
     */
    public function updateCommission(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'aff_percent' => 'required|numeric|min:0|max:100'
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

        $affiliate->aff_percent = $request->aff_percent;
        $affiliate->save();

        return response()->json([
            'success' => true,
            'message' => 'Affiliate commission updated successfully',
            'affiliate' => $affiliate
        ]);
    }
}