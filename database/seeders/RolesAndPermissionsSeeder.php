<?php
// database/seeders/RolesAndPermissionsSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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
            'create affiliate',
            'edit affiliate',
            'delete affiliate',
            'edit affiliate settings',
            'edit affiliate mail',
            
            // Reports
            'view reports',
            
            // Settings
            'view settings',
            'edit settings',
            
            // Dashboard
            'view dashboard',

            // Payment management
            'view payments',
            'make payments',
            'edit payments',
            'delete payments',
            'view payment history',
            'approve payments',
          
            // Games management
            'view games',
            'create games',
            'edit games',
            'delete games',
            
            // Events management
            'view events',
            'create events',
            'edit events',
            'delete events',
            
            // Teams management
            'view teams',
            'create teams',
            'edit teams',
            'delete teams',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        // Super Admin - has all permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        // Admin - has most permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions([
            'view affiliate',
            'create affiliate',
            'edit affiliate',
            'view reports',
            'view dashboard',
            'view settings',
            'edit settings',
            'view payments',
            'make payments',
            'edit payments',
            'view payment history',
            'approve payments',
            'view games',
            'create games',
            'edit games',
            'delete games',
            'view events',
            'create events',
            'edit events',
            'delete events',
            'view teams',
            'create teams',
            'edit teams',
            'delete teams',
        ]);

        // Affiliate User - limited permissions
        $affiliateRole = Role::firstOrCreate(['name' => 'affiliate', 'guard_name' => 'web']);
        $affiliateRole->syncPermissions([
            'view dashboard',
            'edit affiliate settings',
        ]);

        // Create Super Admin User
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@superadmin.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'superadmin@superadmin.com',
                'password' => Hash::make('superadmin@superadmin.com'),
                'address' => '123 Admin Street, NY',
                'balance' => 0,
                'pay_method' => 'bank',
                'account_email' => 'superadmin@superadmin.com',
                'skype' => 'superadmin.skype',
                'company' => 'AfworkMedia Inc',
                'website' => 'https://afworkmedia.com',
                'promotion_description' => 'Super Admin Account',
                'payoneer' => 'superadmin@payoneer.com',
                'paypal' => 'superadmin@paypal.com',
                'sale_hide' => 3,
                'status' => 'active',
            ]
        );
        $superAdmin->syncRoles(['super-admin']);

        // Create Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Admin',
                'email' => 'admin@admin.com',
                'password' => Hash::make('admin@admin.com'),
                'address' => '456 Admin Avenue, LA',
                'balance' => 0,
                'pay_method' => 'paypal',
                'account_email' => 'admin@admin.com',
                'skype' => 'admin.skype',
                'company' => 'AfworkMedia Inc',
                'website' => 'https://afworkmedia.com',
                'promotion_description' => 'Admin Account',
                'payoneer' => 'admin@payoneer.com',
                'paypal' => 'admin@paypal.com',
                'sale_hide' => 3,
                'status' => 'active',
            ]
        );
        $admin->syncRoles(['admin']);

        // Create Affiliate Users
        $affiliate1 = User::updateOrCreate(
            ['email' => 'sarah@sarah.com'],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah@sarah.com',
                'password' => Hash::make('sarah@sarah.com'),
                'address' => '789 Marketing Blvd, Chicago',
                'balance' => 1500,
                'pay_method' => 'paypal',
                'account_email' => 'sarah.payments@sarah.com',
                'skype' => 'sarah.affiliate',
                'company' => 'Sarah Marketing Co',
                'website' => 'https://sarahmarketing.com',
                'promotion_description' => 'Digital marketing specialist',
                'payoneer' => 'sarah@payoneer.com',
                'paypal' => 'sarah@paypal.com',
                'sale_hide' => 3,
                'status' => 'active',
            ]
        );
        $affiliate1->syncRoles(['affiliate']);

        $this->command->info('=========================================');
        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('=========================================');
        $this->command->info('Super Admin: superadmin@superadmin.com');
        $this->command->info('Password: superadmin@superadmin.com');
        $this->command->info('-----------------------------------------');
        $this->command->info('Admin: admin@admin.com');
        $this->command->info('Password: admin@admin.com');
        $this->command->info('-----------------------------------------');
        $this->command->info('Affiliate: sarah@sarah.com');
        $this->command->info('Password: sarah@sarah.com');
        $this->command->info('=========================================');
    }
}