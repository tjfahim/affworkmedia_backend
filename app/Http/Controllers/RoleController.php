<?php
// app/Http/Controllers/RoleController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function index(Request $request)
    {
        try {
            // Only super-admin can view roles
            if (!$request->user() || !$request->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Only super-admin can access roles'
                ], 403);
            }

            $roles = Role::with('permissions')->get();
            
            // Format the response to ensure permissions are properly structured
            $formattedRoles = $roles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->map(function($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ];
                    }),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'roles' => $formattedRoles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all permissions
     */
    public function permissions(Request $request)
    {
        try {
            // Only super-admin can view permissions
            if (!$request->user() || !$request->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Only super-admin can access permissions'
                ], 403);
            }

            $permissions = Permission::all(['id', 'name', 'guard_name']);
            
            return response()->json([
                'success' => true,
                'permissions' => $permissions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update role permissions
     */
    public function updatePermissions(Request $request, $roleId)
    {
        try {
            // Only super-admin can manage permissions
            if (!$request->user() || !$request->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Only super-admin can manage permissions'
                ], 403);
            }

            // Find role by ID
            $role = Role::find($roleId);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found with ID: ' . $roleId
                ], 404);
            }

            // Prevent modifying super-admin role
            if ($role->name === 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify super-admin role permissions'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'permissions' => 'required|array',
                'permissions.*' => 'string|exists:permissions,name'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Sync permissions
            $role->syncPermissions($request->permissions);

            // Reload the role with permissions
            $role->load('permissions');

            return response()->json([
                'success' => true,
                'message' => 'Role permissions updated successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->map(function($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating role permissions: ' . $e->getMessage()
            ], 500);
        }
    }
}