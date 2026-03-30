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
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|url',
            'promotion_description' => 'nullable|string',
            'payoneer' => 'nullable|string|max:255',
            'paypal' => 'nullable|email',
            'role' => 'sometimes|string|in:super-admin,admin,affiliate',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'pay_method' => $request->pay_method,
            'account_email' => $request->account_email,
            'skype' => $request->skype,
            'company' => $request->company,
            'website' => $request->website,
            'promotion_description' => $request->promotion_description,
            'payoneer' => $request->payoneer,
            'paypal' => $request->paypal,
            'balance' => 0,
            'aff_percent' => $request->role === 'affiliate' ? 5 : 0, // Default 5% for affiliates
            'sale_add' => true,
            'auto_renew' => false,
            'sale_hide' => false,
            'status' => 'inactive',
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
            'aff_percent' => 'sometimes|numeric|min:0|max:100',
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
            'aff_percent' => $request->aff_percent ?? ($request->role === 'affiliate' ? 5 : 0),
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
            'aff_percent' => 'sometimes|numeric|min:0|max:100',
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
            'aff_percent' => 'sometimes|numeric|min:0|max:100',
            'sale_add' => 'sometimes|boolean',
            'auto_renew' => 'sometimes|boolean',
            'sale_hide' => 'sometimes|boolean',
            'paypal' => 'nullable|email',
            'payoneer' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only([
            'aff_percent',
            'sale_add',
            'auto_renew',
            'sale_hide',
            'paypal',
            'payoneer'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Affiliate settings updated successfully',
            'user' => $user->fresh()
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