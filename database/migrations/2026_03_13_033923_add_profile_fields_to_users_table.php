<?php
// database/migrations/2024_01_01_000000_add_profile_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Personal Information
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('address')->nullable()->after('email');
            
            // Financial Information
            $table->decimal('balance', 10, 2)->default(0)->after('address');
            $table->string('pay_method')->nullable()->after('balance');
            $table->string('account_email')->nullable()->after('pay_method');
            
            // Professional Information
            $table->string('skype')->nullable()->after('account_email');
            $table->string('company')->nullable()->after('skype');
            $table->string('website')->nullable()->after('company');
            $table->text('promotion_description')->nullable()->after('website');
            
            // Payment Details
            $table->string('payoneer')->nullable()->after('promotion_description');
            $table->string('paypal')->nullable()->after('payoneer');
            
            // Affiliate Settings
            $table->decimal('aff_percent', 5, 2)->default(0)->after('paypal');
            $table->boolean('sale_add')->default(true)->after('aff_percent');
            $table->boolean('auto_renew')->default(false)->after('sale_add');
            $table->boolean('sale_hide')->default(false)->after('auto_renew');
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('sale_hide');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'address', 'balance', 'pay_method',
                'account_email', 'skype', 'company', 'website', 'promotion_description',
                'payoneer', 'paypal', 'aff_percent', 'sale_add', 'auto_renew',
                'sale_hide', 'status'
            ]);
        });
    }
};