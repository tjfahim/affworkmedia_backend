<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\AffiliateSale;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    /**
     * Get all users with their roles and permissions
     */
    public function index(Request $request)
    {
        // Check if user has permission to view users
        if (!$request->user()->hasPermissionTo('view users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view users'
            ], 403);
        }

        $users = User::with('roles', 'permissions')->get();
        
        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Create a new user (admin/super-admin only)
     */
   /**
 * Create user (Admin only)
 */
public function store(Request $request)
{
    // Check if user has permission to create users
    if (!$request->user()->hasPermissionTo('create users')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized to create users'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
        'address' => 'nullable|string',
        'pay_method' => 'nullable|string|in:paypal,payoneer,bank,binance,other',
        'account_email' => 'nullable|email',
        'skype' => 'nullable|string|max:255',
        'phone_number' => 'nullable|string|max:255',
        'telegram_account' => 'nullable|string|max:255',
        'microsoft_team' => 'nullable|string|max:255',
        'company' => 'nullable|string|max:255',
        'website' => 'nullable|url',
        'promotion_description' => 'nullable|string',
        'payoneer' => 'nullable|string|max:255',
        'paypal' => 'nullable|email',
        'binance' => 'nullable|string|max:255',
        'bank_details' => 'nullable|string',
        'other_payment_method_description' => 'nullable|string',
        'role' => 'required|string|in:admin,affiliate,super-admin',
        'status' => 'required|in:active,inactive,suspended',
        'default_affiliate_commission_1' => 'nullable|numeric|min:0|max:100',
        'default_affiliate_commission_2' => 'nullable|numeric|min:0|max:100',
        'default_affiliate_commission_3' => 'nullable|numeric|min:0|max:100',
        'sale_hide' => 'nullable|integer|min:0|max:10',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Restrict role assignment based on user's role
    $currentUser = $request->user();
    $requestedRole = $request->role;
    
    // Only super-admin can create admin or super-admin users
    if (($requestedRole === 'admin' || $requestedRole === 'super-admin') && !$currentUser->hasRole('super-admin')) {
        return response()->json([
            'success' => false,
            'message' => 'Only super-admin can create admin or super-admin users'
        ], 403);
    }
    
    // Admin users can only create affiliate users
    if ($currentUser->hasRole('admin') && $requestedRole !== 'affiliate') {
        return response()->json([
            'success' => false,
            'message' => 'Admin users can only create affiliate accounts'
        ], 403);
    }

    // Get settings for default commission values
    $settings = Setting::getSettings();

    $user = User::create([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'address' => $request->address,
        'phone_number' => $request->phone_number,
        'telegram_account' => $request->telegram_account,
        'microsoft_team' => $request->microsoft_team,
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
        'balance' => 0, // Always start with 0 balance
        'default_affiliate_commission_1' => $request->default_affiliate_commission_1 ?? ($settings->default_affiliate_commission_1 ?? 70),
        'default_affiliate_commission_2' => $request->default_affiliate_commission_2 ?? ($settings->default_affiliate_commission_2 ?? 50),
        'default_affiliate_commission_3' => $request->default_affiliate_commission_3 ?? ($settings->default_affiliate_commission_3 ?? 40),
        'sale_hide' => $request->sale_hide ?? 3,
        'status' => $request->status,
        // Default statuses for payment methods
        'edit_paypal_mail_status' => 'active',
        'edit_payoneer_mail_status' => 'active',
        'edit_bank_details_status' => 'active',
        'edit_binance_mail_status' => 'active',
        'edit_other_payment_method_description_status' => 'active',
        'total_earnings' => 0,
        'total_sales' => 0,
        'unique_clicks' => 0,
        'total_clicks' => 0,
    ]);

    // Assign role
    $user->assignRole($requestedRole);

    return response()->json([
        'success' => true,
        'message' => 'User created successfully',
        'user' => $user->load('roles', 'permissions')
    ], 201);
}

    /**
     * Get single user details
     */
    public function show(Request $request, $id)
    {
        // Check if user has permission to view users
        if (!$request->user()->hasPermissionTo('view users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view users'
            ], 403);
        }

        $user = User::with('roles', 'permissions')->find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

 /**
 * Update user (Admin only)
 */
public function update(Request $request, $id)
{
    if (!$request->user()->can('edit users')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
        'role' => 'sometimes|string|in:admin,affiliate',
        'status' => 'sometimes|in:active,inactive,suspended',
        'address' => 'nullable|string',
        'pay_method' => 'nullable|string|in:paypal,payoneer,bank,binance,other',
        'account_email' => 'nullable|email',
        'phone_number' => 'nullable|string|max:255',
        'telegram_account' => 'nullable|string|max:255',
        'microsoft_team' => 'nullable|string|max:255',
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
        'sale_hide' => 'nullable|integer|min:0|max:10',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Restrict role assignment based on user's role
    $currentUser = $request->user();
    
    if ($request->has('role')) {
        $requestedRole = $request->role;
        
        // Only super-admin can change role to admin
        if ($requestedRole === 'admin' && !$currentUser->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only super-admin can assign admin role'
            ], 403);
        }
        
        // Admin users can only assign affiliate role
        if ($currentUser->hasRole('admin') && $requestedRole !== 'affiliate') {
            return response()->json([
                'success' => false,
                'message' => 'Admin users can only assign affiliate role'
            ], 403);
        }
        
        // Update role
        $user->syncRoles([$requestedRole]);
    }

    // Remove balance from update data - prevent balance modification
    $updateData = $request->except('role', 'password', 'balance');
    
    // Update user data
    $user->update($updateData);

    // Update password if provided
    if ($request->has('password') && !empty($request->password)) {
        $user->update(['password' => Hash::make($request->password)]);
    }

    return response()->json([
        'success' => true,
        'message' => 'User updated successfully',
        'user' => $user->fresh()->load('roles', 'permissions')
    ]);
}
    /**
     * Toggle user status (activate/deactivate)
     */
    public function toggleStatus(Request $request, $id)
    {
        // Only super-admin and admin can toggle status
        if (!$request->user()->hasPermissionTo('edit users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to change user status'
            ], 403);
        }

        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent toggling super-admin status
        if ($user->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change super-admin user status'
            ], 403);
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->status = $newStatus;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "User status changed to {$newStatus}",
            'user' => $user->load('roles', 'permissions')
        ]);
    }

    /**
     * Delete user (super-admin only)
     */
    public function destroy(Request $request, $id)
    {
        // Only super-admin can delete users
        if (!$request->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete users'
            ], 403);
        }

        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent deleting super-admin
        if ($user->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super-admin user'
            ], 403);
        }

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get all available roles (for dropdown)
     */
    public function getRoles(Request $request)
    {
        // Only admin/super-admin can access
        if (!$request->user()->hasPermissionTo('view users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Exclude super-admin role from being assignable
        $roles = Role::where('name', '!=', 'super-admin')->get();
        
        return response()->json([
            'success' => true,
            'roles' => $roles
        ]);
    }
}