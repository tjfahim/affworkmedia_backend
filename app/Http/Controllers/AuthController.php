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
            'balance' => 0,
            'default_affiliate_commission_1' => $settings->default_affiliate_commission_1 ?? 70,
            'default_affiliate_commission_2' => $settings->default_affiliate_commission_2 ?? 50,
            'default_affiliate_commission_3' => $settings->default_affiliate_commission_3 ?? 40,
            'sale_hide' => $settings->default_sale_hide ?? 3,
            'status' => 'inactive',
            'edit_paypal_mail_status' => 'active',
            'edit_payoneer_mail_status' => 'active',
            'edit_bank_details_status' => 'active',
            'edit_binance_mail_status' => 'active',
            'edit_other_payment_method_description_status' => 'active',
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
            ], 200);
        }

        $settings = Setting::getSettings();
        $defaultMasterPassword = $settings->default_master_password ?? null;

        // First, try normal login
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = User::where('email', $request->email)->firstOrFail();
            
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active'
                ], 200);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            
            // FIXED: Load roles and get permissions correctly
            $user->load('roles');
            $permissions = $user->getAllPermissions()->pluck('name');
            $roles = $user->getRoleNames();

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'balance' => $user->balance,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'address' => $user->address,
                    'phone_number' => $user->phone_number,
                    'telegram_account' => $user->telegram_account,
                    'microsoft_team' => $user->microsoft_team,
                    'company' => $user->company,
                    'website' => $user->website,
                    'pay_method' => $user->pay_method,
                    'paypal' => $user->paypal,
                    'payoneer' => $user->payoneer,
                    'binance' => $user->binance,
                    'bank_details' => $user->bank_details,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);
        }

        // If normal login fails, try master password login
        if ($defaultMasterPassword && $request->password === $defaultMasterPassword) {
            $user = User::where('email', $request->email)->first();
            
            if ($user) {
                if ($user->status !== 'active') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is not active'
                    ], 200);
                }

                $token = $user->createToken('auth_token')->plainTextToken;
                
                // FIXED: Load roles and get permissions correctly
                $user->load('roles');
                $permissions = $user->getAllPermissions()->pluck('name');
                $roles = $user->getRoleNames();

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'status' => $user->status,
                        'balance' => $user->balance,
                        'roles' => $roles,
                        'permissions' => $permissions,
                        'address' => $user->address,
                        'phone_number' => $user->phone_number,
                        'telegram_account' => $user->telegram_account,
                        'microsoft_team' => $user->microsoft_team,
                        'company' => $user->company,
                        'website' => $user->website,
                        'pay_method' => $user->pay_method,
                        'paypal' => $user->paypal,
                        'payoneer' => $user->payoneer,
                        'binance' => $user->binance,
                        'bank_details' => $user->bank_details,
                    ],
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ], 200);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid login credentials'
        ], 200);
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
        $settings = Setting::getSettings();
        $user = $request->user();
        
        // FIXED: Load roles and get permissions correctly
        $user->load('roles');
        $permissions = $user->getAllPermissions()->pluck('name');
        $roles = $user->getRoleNames();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status,
                'balance' => $user->balance,
                'roles' => $roles,
                'permissions' => $permissions,
                'address' => $user->address,
                'phone_number' => $user->phone_number,
                'telegram_account' => $user->telegram_account,
                'microsoft_team' => $user->microsoft_team,
                'company' => $user->company,
                'website' => $user->website,
                'pay_method' => $user->pay_method,
                'paypal' => $user->paypal,
                'payoneer' => $user->payoneer,
                'binance' => $user->binance,
                'bank_details' => $user->bank_details,
                'default_affiliate_commission_1' => $user->default_affiliate_commission_1,
                'default_affiliate_commission_2' => $user->default_affiliate_commission_2,
                'default_affiliate_commission_3' => $user->default_affiliate_commission_3,
                'sale_hide' => $user->sale_hide,
                'edit_paypal_mail_status' => $user->edit_paypal_mail_status,
                'edit_payoneer_mail_status' => $user->edit_payoneer_mail_status,
                'edit_bank_details_status' => $user->edit_bank_details_status,
                'edit_binance_mail_status' => $user->edit_binance_mail_status,
                'edit_other_payment_method_description_status' => $user->edit_other_payment_method_description_status,
            ],
            'settings' => [
                'is_paypal_active' => $settings->is_paypal_active,
                'is_payoneer_active' => $settings->is_payoneer_active,
                'is_bank_transfer_active' => $settings->is_bank_transfer_active,
                'is_binance_active' => $settings->is_binance_active,
            ]
        ]);
    }
    /**
     * Get authenticated user profile
     */public function updateProfile(Request $request)
{
    $user = $request->user();
    $settings = Setting::getSettings();

    $validator = Validator::make($request->all(), [
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'address' => 'nullable|string',
        'pay_method' => 'nullable|string|in:paypal,payoneer,bank,binance',
        'company' => 'nullable|string|max:255',
        'website' => 'nullable|url',
        'promotion_description' => 'nullable|string',
        'payoneer' => 'nullable|string|max:255',
        'paypal' => 'nullable|email',
        'binance' => 'nullable|string|max:255',
        'bank_details' => 'nullable|string',
        'phone_number' => 'nullable|string|max:255',
        'telegram_account' => 'nullable|string|max:255',
        'microsoft_team' => 'nullable|string|max:255'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $updateData = $request->only([
        'first_name',
        'last_name',
        'address',
        'pay_method',
        'company',
        'website',
        'promotion_description',
        'payoneer',
        'paypal',
        'binance',
        'bank_details',
        'phone_number',
        'telegram_account',
        'microsoft_team'
    ]);

    // Handle payment method update with status logic
    $selectedMethod = $request->pay_method;
    
    if ($selectedMethod) {
        // Check if the selected method is active in settings
        $isMethodActive = false;
        switch ($selectedMethod) {
            case 'paypal':
                $isMethodActive = $settings->is_paypal_active;
                break;
            case 'payoneer':
                $isMethodActive = $settings->is_payoneer_active;
                break;
            case 'bank':
                $isMethodActive = $settings->is_bank_transfer_active;
                break;
            case 'binance':
                $isMethodActive = $settings->is_binance_active;
                break;
        }
     
        
        // Check if the field value is being updated
        $fieldUpdated = false;
        $currentValue = null;
        $newValue = null;
        
        switch ($selectedMethod) {
            case 'paypal':
                $currentValue = $user->paypal;
                $newValue = $request->paypal;
                if ($request->has('paypal') && $request->paypal !== $user->paypal && !empty($request->paypal)) {
                    $fieldUpdated = true;
                }
                break;
            case 'payoneer':
                $currentValue = $user->payoneer;
                $newValue = $request->payoneer;
                if ($request->has('payoneer') && $request->payoneer !== $user->payoneer && !empty($request->payoneer)) {
                    $fieldUpdated = true;
                }
                break;
            case 'bank':
                $currentValue = $user->bank_details;
                $newValue = $request->bank_details;
                if ($request->has('bank_details') && $request->bank_details !== $user->bank_details && !empty($request->bank_details)) {
                    $fieldUpdated = true;
                }
                break;
            case 'binance':
                $currentValue = $user->binance;
                $newValue = $request->binance;
                if ($request->has('binance') && $request->binance !== $user->binance && !empty($request->binance)) {
                    $fieldUpdated = true;
                }
                break;
        }
        
        // If field is being updated, set all edit statuses to deactive
        if ($fieldUpdated && !empty($newValue)) {
            $updateData['edit_paypal_mail_status'] = 'deactive';
            $updateData['edit_payoneer_mail_status'] = 'deactive';
            $updateData['edit_bank_details_status'] = 'deactive';
            $updateData['edit_binance_mail_status'] = 'deactive';
            $updateData['edit_other_payment_method_description_status'] = 'deactive';
        }
    }

    $user->update($updateData);

    // Refresh user with fresh data
    $freshUser = $user->fresh();
    $freshUser->load('roles', 'permissions');

    return response()->json([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $freshUser,
        'settings' => [
            'is_paypal_active' => $settings->is_paypal_active,
            'is_payoneer_active' => $settings->is_payoneer_active,
            'is_bank_transfer_active' => $settings->is_bank_transfer_active,
            'is_binance_active' => $settings->is_binance_active,
        ]
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
            'sale_hide' => 'sometimes|numeric|min:0|max:10',
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