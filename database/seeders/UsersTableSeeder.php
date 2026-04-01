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
            'balance' => 10000,
            'pay_method' => 'bank',
            'account_email' => 'superadmin@superadmin.com',
            'skype' => 'superadmin.skype',
            'company' => 'AfworkMedia Inc',
            'website' => 'https://afworkmedia.com',
            'promotion_description' => 'Super Admin Account',
            'payoneer' => 'superadmin@payoneer.com',
            'paypal' => 'superadmin@paypal.com',
            'aff_percent' => 0,
            'sale_add' => true,
            'auto_renew' => true,
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
            'balance' => 5000,
            'pay_method' => 'paypal',
            'account_email' => 'admin@admin.com',
            'skype' => 'admin.skype',
            'company' => 'AfworkMedia Inc',
            'website' => 'https://afworkmedia.com',
            'promotion_description' => 'Admin Account',
            'payoneer' => 'admin@payoneer.com',
            'paypal' => 'admin@paypal.com',
            'aff_percent' => 0,
            'sale_add' => true,
            'auto_renew' => true,
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
            'balance' => 1250.50,
            'pay_method' => 'paypal',
            'account_email' => 'sarah.payments@sarah.com',
            'skype' => 'sarah.affiliate',
            'company' => 'Sarah Marketing Co',
            'website' => 'https://sarahmarketing.com',
            'promotion_description' => 'Digital marketing specialist focusing on tech products',
            'payoneer' => 'sarah@payoneer.com',
            'paypal' => 'sarah@paypal.com',
            'aff_percent' => 10,
            'sale_add' => true,
            'auto_renew' => false,
            'sale_hide' => 3,
            'status' => 'active',
        ]);
        $affiliate1->assignRole('affiliate');

        // Create Affiliate User 2
        $affiliate2 = User::create([
            'first_name' => 'Mike',
            'last_name' => 'Chen',
            'email' => 'mike@mike.com',
            'password' => Hash::make('mike@mike.com'),
            'address' => '321 Digital Way, SF',
            'balance' => 3450.75,
            'pay_method' => 'payoneer',
            'account_email' => 'mike.chen@mike.com',
            'skype' => 'mike.chen.aff',
            'company' => 'Chen Digital Solutions',
            'website' => 'https://chendigital.com',
            'promotion_description' => 'Tech reviewer and affiliate marketer for software products',
            'payoneer' => 'mike@payoneer.com',
            'paypal' => 'mike@paypal.com',
            'aff_percent' => 15,
            'sale_add' => true,
            'auto_renew' => true,
            'sale_hide' => 3,
            'status' => 'active',
        ]);
        $affiliate2->assignRole('affiliate');

        // Create Affiliate User 3 (inactive)
        $affiliate3 = User::create([
            'first_name' => 'Emma',
            'last_name' => 'Wilson',
            'email' => 'emma@emma.com',
            'password' => Hash::make('emma@emma.com'),
            'address' => '555 Marketing Ave, Austin',
            'balance' => 0,
            'pay_method' => 'paypal',
            'account_email' => 'emma.wilson@emma.com',
            'skype' => 'emma.wilson',
            'company' => 'Wilson Promotions',
            'website' => 'https://wilsonpromo.com',
            'promotion_description' => 'Social media influencer and affiliate marketer',
            'payoneer' => 'emma@payoneer.com',
            'paypal' => 'emma@paypal.com',
            'aff_percent' => 8,
            'sale_add' => false,
            'auto_renew' => false,
            'sale_hide' => 3,
            'status' => 'inactive',
        ]);
        $affiliate3->assignRole('affiliate');

        $this->command->info('Users created successfully!');
        $this->command->info('Super Admin: superadmin@superadmin.com / superadmin@superadmin.com');
        $this->command->info('Admin: admin@admin.com / admin@admin.com');
        $this->command->info('Affiliate Users: sarah@sarah.com, mike@mike.com, emma@emma.com / emma@emma.com');
    }
}