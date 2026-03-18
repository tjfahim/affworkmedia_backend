<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\User;
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
     * Create a new user (super-admin only)
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
            'status' => 'sometimes|in:active,inactive'
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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => $request->status ?? 'active'
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
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|exists:roles,name',
            'status' => 'sometimes|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user data
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }
        if ($request->has('status')) {
            // Only super-admin can change status
            if (!$request->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super-admin can change user status'
                ], 403);
            }
            $user->status = $request->status;
        }

        $user->save();

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
}