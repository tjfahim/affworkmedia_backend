<?php
// database/seeders/RolesAndPermissionsSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Affiliate management
            'view affiliate',
            'manage affiliate',
            'edit affiliate settings',
            
            // Reports
            'view reports',
            'export reports',
            
            // Settings
            'view settings',
            'edit settings',
            
            // Dashboard
            'view dashboard',
            'view analytics',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - has all permissions
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        // Admin - has most permissions except some super admin specific ones
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'view users',
            'create users',
            'edit users',
            'view affiliate',
            'manage affiliate',
            'view reports',
            'export reports',
            'view dashboard',
            'view analytics',
        ]);

        // Affiliate User - limited permissions
        $affiliateRole = Role::create(['name' => 'affiliate']);
        $affiliateRole->givePermissionTo([
            'view dashboard',
            'edit affiliate settings',
        ]);

        $this->command->info('Roles and permissions created successfully!');
    }
}