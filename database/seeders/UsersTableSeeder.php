<?php
// database/seeders/UsersTableSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        // Create Super Admin
        $superAdmin = User::create([
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
        ]);
        $superAdmin->assignRole('super-admin');

        // Create Admin
        $admin = User::create([
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
        ]);
        $admin->assignRole('admin');

        // Create Affiliate User 1
        $affiliate1 = User::create([
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'email' => 'sarah@sarah.com',
            'password' => Hash::make('sarah@sarah.com'),
            'address' => '789 Marketing Blvd, Chicago',
            'balance' => 0,
            'pay_method' => 'paypal',
            'account_email' => 'sarah.payments@sarah.com',
            'skype' => 'sarah.affiliate',
            'company' => 'Sarah Marketing Co',
            'website' => 'https://sarahmarketing.com',
            'promotion_description' => 'Digital marketing specialist focusing on tech products',
            'payoneer' => 'sarah@payoneer.com',
            'paypal' => 'sarah@paypal.com',
            'sale_hide' => 3,
            'status' => 'active',
        ]);
        $affiliate1->assignRole('affiliate');

      
        $this->command->info('Users created successfully!');
        $this->command->info('Super Admin: superadmin@superadmin.com / superadmin@superadmin.com');
        $this->command->info('Admin: admin@admin.com / admin@admin.com');
        $this->command->info('Affiliate Users: sarah@sarah.com, mike@mike.com, emma@emma.com / emma@emma.com');
    }
}