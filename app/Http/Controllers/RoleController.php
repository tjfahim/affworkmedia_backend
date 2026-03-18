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
        // Only super-admin can view roles
        if (!$request->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $roles = Role::with('permissions')->get();
        
        return response()->json([
            'success' => true,
            'roles' => $roles
        ]);
    }

    /**
     * Get all permissions
     */
    public function permissions(Request $request)
    {
        // Only super-admin can view permissions
        if (!$request->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $permissions = Permission::all();
        
        return response()->json([
            'success' => true,
            'permissions' => $permissions
        ]);
    }

    /**
     * Update role permissions
     */
  public function updatePermissions(Request $request, $roleId)
    {
        // Only super-admin can manage permissions
        if (!$request->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to manage permissions'
            ], 403);
        }

        try {
            // Try to find by ID without guard_name first
            $role = Role::find($roleId);
            
            if (!$role) {
                // If not found, try with the default guard
                try {
                    $role = Role::findById($roleId, 'web');
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Role not found with ID: ' . $roleId
                    ], 404);
                }
            }

            // Prevent modifying super-admin role
            if ($role->name === 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify super-admin role'
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
                'role' => $role
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating role permissions: ' . $e->getMessage()
            ], 500);
        }
    }
}