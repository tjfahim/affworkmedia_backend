<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'address' => 'nullable|string',
            'pay_method' => 'nullable|string|in:paypal,payoneer,bank',
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
            'role' => 'sometimes|string|in:super-admin,admin,affiliate',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

               $settings = Setting::getSettings(); // <-- Add this line

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
        'balance' => 0,
        'default_affiliate_commission_1' => $settings->default_affiliate_commission_1 ?? 70,
        'default_affiliate_commission_2' => $settings->default_affiliate_commission_2 ?? 50,
        'default_affiliate_commission_3' => $settings->default_affiliate_commission_3 ?? 40,
    
        'sale_hide' => 3,
        'status' => 'inactive',
        // Default statuses for payment methods
        'edit_paypal_mail_status' => 'deactive',
        'edit_payoneer_mail_status' => 'deactive',
        'edit_bank_details_status' => 'deactive',
        'edit_binance_mail_status' => 'deactive',
        'edit_other_payment_method_description_status' => 'deactive',
    ]);
        // Assign role
        $role = $request->role ?? 'affiliate';
        $user->assignRole($role);

        $token = $user->createToken('auth_token')->plainTextToken;
        if ($user->status !== 'active') {
            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Your account is not active yet. Please wait for admin approval.',
            ], 403);
        }
        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'user' => $user->load('roles'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login user
     */
     public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 200); // Change to 200 to prevent browser navigation issues
    }

    // Get settings to check for default master password
    $settings = Setting::getSettings();
    $defaultMasterPassword = $settings->default_master_password ?? null;

    // First, try normal login
    if (Auth::attempt($request->only('email', 'password'))) {
        $user = User::where('email', $request->email)->firstOrFail();
        
        // Check if user is active
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active'
            ], 200); // Change to 200
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    // If normal login fails, try master password login
    if ($defaultMasterPassword && $request->password === $defaultMasterPassword) {
        // Find user by email
        $user = User::where('email', $request->email)->first();
        
        if ($user) {
            // Check if user is active
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active'
                ], 200); // Change to 200
            }

            // Login successful with master password
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->load(['roles.permissions']);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);
        }
    }

    // If we get here, login failed - return 200 with success=false
    return response()->json([
        'success' => false,
        'message' => 'Invalid login credentials'
    ], 200); // Change to 200 to prevent browser from treating it as an error
}

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()->load('roles', 'permissions')
        ]);
    }

    /**
     * Update authenticated user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

         $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'pay_method' => 'nullable|string|in:paypal,payoneer,bank',
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
            'phone_number' => 'nullable|string|max:255',
            'telegram_account' => 'nullable|string|max:255',
            'microsoft_team' => 'nullable|string|max:255'

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

         $user->update($request->only([
            'first_name',
            'last_name',
            'address',
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
            'phone_number',
            'telegram_account',
            'microsoft_team'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->load('roles', 'permissions')
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Get all users (Admin only)
     */
    public function index(Request $request)
    {
        // Check if user has permission
        if (!$request->user()->can('view users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $users = User::with('roles', 'permissions')
            ->when($request->role, function($query, $role) {
                return $query->role($role);
            })
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->search, function($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Get single user (Admin only)
     */
    public function show(Request $request, $id)
    {
        if (!$request->user()->can('view users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
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
     * Create user (Admin only)
     */
    public function store(Request $request)
    {
        if (!$request->user()->can('create users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:super-admin,admin,affiliate',
            'status' => 'sometimes|in:active,inactive,suspended',
            'balance' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'balance' => $request->balance ?? 0,
            'status' => $request->status ?? 'active',
        ]);

        // Assign role
        $user->assignRole($request->role);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $user->load('roles')
        ], 201);
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
            'role' => 'sometimes|string|in:super-admin,admin,affiliate',
            'status' => 'sometimes|in:active,inactive,suspended',
            'balance' => 'sometimes|numeric|min:0',
            'address' => 'nullable|string',
            'pay_method' => 'nullable|string|in:paypal,payoneer,bank',
            'account_email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user data
        $user->update($request->except('role', 'password'));

        // Update password if provided
        if ($request->has('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Update role if provided
        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user->fresh()->load('roles', 'permissions')
        ]);
    }

    /**
     * Delete user (Admin only)
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->can('delete users')) {
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

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Update affiliate settings (Admin or self)
     */
    public function updateAffiliateSettings(Request $request, $id = null)
    {
        $user = $id ? User::find($id) : $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check permission: admin can update any, users can only update their own
        if ($id && !$request->user()->can('manage affiliate') && $request->user()->id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'default_affiliate_commission_1' => 'sometimes|numeric|min:0|max:100',
            'default_affiliate_commission_2' => 'sometimes|numeric|min:0|max:100',
            'default_affiliate_commission_3' => 'sometimes|numeric|min:0|max:100',
            'sale_hide' => 'sometimes|numeric|min:0|max:100',
            'paypal' => 'nullable|email',
            'payoneer' => 'nullable|string',
            'binance' => 'nullable|string',
            'bank_details' => 'nullable|string',
            'other_payment_method_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only([
            'default_affiliate_commission_1',
            'default_affiliate_commission_2',
            'default_affiliate_commission_3',
            'sale_hide',
            'paypal',
            'payoneer',
            'binance',
            'bank_details',
            'other_payment_method_description',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Affiliate settings updated successfully',
                        'user' => $user->fresh()->load('roles', 'permissions')

        ]);
    }


      public function updatePaymentStatus(Request $request, $id)
    {
        if (!$request->user()->can('manage affiliate')) {
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
            'edit_paypal_mail_status' => 'sometimes|in:active,deactive,requested',
            'edit_payoneer_mail_status' => 'sometimes|in:active,deactive,requested',
            'edit_bank_details_status' => 'sometimes|in:active,deactive,requested',
            'edit_binance_mail_status' => 'sometimes|in:active,deactive,requested',
            'edit_other_payment_method_description_status' => 'sometimes|in:active,deactive,requested',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only([
            'edit_paypal_mail_status',
            'edit_payoneer_mail_status',
            'edit_bank_details_status',
            'edit_binance_mail_status',
            'edit_other_payment_method_description_status',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Payment method statuses updated successfully',
            'user' => $user->fresh()->load('roles', 'permissions')

        ]);
    }

    public function requestPaymentMethodChange(Request $request)
{
    $user = $request->user();
    
    $validator = Validator::make($request->all(), [
        'payment_type' => 'required|in:paypal,payoneer,bank,binance,other',
        'value' => 'nullable|string', // Changed from 'required' to 'nullable'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $statusField = '';
    $valueField = '';

    switch ($request->payment_type) {
        case 'paypal':
            $statusField = 'edit_paypal_mail_status';
            $valueField = 'paypal';
            break;
        case 'payoneer':
            $statusField = 'edit_payoneer_mail_status';
            $valueField = 'payoneer';
            break;
        case 'bank':
            $statusField = 'edit_bank_details_status';
            $valueField = 'bank_details';
            break;
        case 'binance':
            $statusField = 'edit_binance_mail_status';
            $valueField = 'binance';
            break;
        case 'other':
            $statusField = 'edit_other_payment_method_description_status';
            $valueField = 'other_payment_method_description';
            break;
    }

    // Update the payment method value (can be empty) and set status to requested
    $updateData = [
        $statusField => 'requested'
    ];
    
    // Only update the value field if it's provided (not null)
    if ($request->has('value') && $request->value !== null) {
        $updateData[$valueField] = $request->value;
    }
    
    $user->update($updateData);

    return response()->json([
        'success' => true,
        'message' => 'Payment method change request submitted successfully',
        'user' => $user->fresh()->load('roles', 'permissions')
    ]);
}
  public function getPendingPaymentRequests(Request $request)
    {
        if (!$request->user()->can('manage affiliate')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $users = User::withScope('withPendingPaymentRequests')
            ->with('roles')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }
    /**
     * Bulk update users (Admin only)
     */
    public function bulkUpdate(Request $request)
    {
        if (!$request->user()->can('edit users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'status' => 'sometimes|in:active,inactive,suspended',
            'role' => 'sometimes|string|in:super-admin,admin,affiliate',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $users = User::whereIn('id', $request->user_ids);

        if ($request->has('status')) {
            $users->update(['status' => $request->status]);
        }

        if ($request->has('role')) {
            foreach ($users->get() as $user) {
                $user->syncRoles([$request->role]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Users updated successfully'
        ]);
    }
}