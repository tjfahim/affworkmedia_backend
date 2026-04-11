<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

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
            'skype_account' => 'nullable|string|max:255',
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
            'role' => 'required|string|in:admin,affiliate',
            'status' => 'required|in:active,inactive,suspended',
            'balance' => 'nullable|numeric|min:0',
            'default_affiliate_commission_1' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_2' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_3' => 'nullable|numeric|min:0|max:100',
            'sale_hide' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Only super-admin can create admin users
        if ($request->role === 'admin' && !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only super-admin can create admin users'
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
            'skype' => $request->skype ?? $request->skype_account,
            'company' => $request->company,
            'website' => $request->website,
            'promotion_description' => $request->promotion_description,
            'payoneer' => $request->payoneer,
            'paypal' => $request->paypal,
            'binance' => $request->binance,
            'bank_details' => $request->bank_details,
            'other_payment_method_description' => $request->other_payment_method_description,
            'balance' => $request->balance ?? 0,
            'default_affiliate_commission_1' => $request->default_affiliate_commission_1 ?? ($settings->default_affiliate_commission_1 ?? 70),
            'default_affiliate_commission_2' => $request->default_affiliate_commission_2 ?? ($settings->default_affiliate_commission_2 ?? 50),
            'default_affiliate_commission_3' => $request->default_affiliate_commission_3 ?? ($settings->default_affiliate_commission_3 ?? 40),
            'sale_hide' => $request->sale_hide ?? 3,
            'status' => $request->status,
            // Default statuses for payment methods
            'edit_paypal_mail_status' => 'deactive',
            'edit_payoneer_mail_status' => 'deactive',
            'edit_bank_details_status' => 'deactive',
            'edit_binance_mail_status' => 'deactive',
            'edit_other_payment_method_description_status' => 'deactive',
        ]);

        // Assign role
        $user->assignRole($request->role);

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
     * Update user
     */
    public function update(Request $request, $id)
    {
        // Check if user has permission to edit users
        if (!$request->user()->hasPermissionTo('edit users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit users'
            ], 403);
        }

        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if trying to update super-admin
        if ($user->hasRole('super-admin') && !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify super-admin user'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8|confirmed',
            'address' => 'nullable|string',
            'pay_method' => 'nullable|string|in:paypal,payoneer,bank,binance,other',
            'account_email' => 'nullable|email',
            'skype' => 'nullable|string|max:255',
            'skype_account' => 'nullable|string|max:255',
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
            'role' => 'sometimes|string|exists:roles,name',
            'status' => 'sometimes|in:active,inactive,suspended',
            'balance' => 'nullable|numeric|min:0',
            'default_affiliate_commission_1' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_2' => 'nullable|numeric|min:0|max:100',
            'default_affiliate_commission_3' => 'nullable|numeric|min:0|max:100',
            'sale_hide' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user data
        $updateData = $request->only([
            'first_name',
            'last_name',
            'address',
            'pay_method',
            'account_email',
            'skype',
            'phone_number',
            'telegram_account',
            'microsoft_team',
            'company',
            'website',
            'promotion_description',
            'payoneer',
            'paypal',
            'binance',
            'bank_details',
            'other_payment_method_description',
            'balance',
            'default_affiliate_commission_1',
            'default_affiliate_commission_2',
            'default_affiliate_commission_3',
            'sale_hide',
        ]);

        // Handle skype_account field (support both field names)
        if ($request->has('skype_account') && !$request->has('skype')) {
            $updateData['skype'] = $request->skype_account;
        }

        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }

        if ($request->has('password') && !empty($request->password)) {
            $updateData['password'] = Hash::make($request->password);
        }

        if ($request->has('status')) {
            // Only super-admin can change status
            if (!$request->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super-admin can change user status'
                ], 403);
            }
            $updateData['status'] = $request->status;
        }

        $user->update($updateData);

        // Update role if provided
        if ($request->has('role')) {
            // Only super-admin can change roles
            if (!$request->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super-admin can change user roles'
                ], 403);
            }
            
            // Prevent changing super-admin role
            if ($user->hasRole('super-admin') && $request->role !== 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change super-admin role'
                ], 403);
            }
            
            $user->syncRoles([$request->role]);
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