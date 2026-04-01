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
            $table->integer('sale_add')->default(0)->after('aff_percent');
            $table->boolean('auto_renew')->default(false)->after('sale_add');
            $table->integer('sale_hide')->default(0)->after('auto_renew');
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('sale_hide');
               // Affiliate Commissions
            $table->decimal('default_affiliate_commission_1', 5, 2)->default(0)->after('aff_percent');
            $table->decimal('default_affiliate_commission_2', 5, 2)->default(0)->after('default_affiliate_commission_1');
            $table->decimal('default_affiliate_commission_3', 5, 2)->default(0)->after('default_affiliate_commission_2');
            
            // Bank Details
            $table->text('bank_details')->nullable()->after('default_affiliate_commission_3');
            
            // Payment Method Statuses
            $table->enum('edit_paypal_mail_status', ['active', 'deactive', 'requested'])->default('deactive')->after('bank_details');
            $table->enum('edit_payoneer_mail_status', ['active', 'deactive', 'requested'])->default('deactive')->after('edit_paypal_mail_status');
            $table->enum('edit_bank_details_status', ['active', 'deactive', 'requested'])->default('deactive')->after('edit_payoneer_mail_status');
            $table->enum('edit_binance_mail_status', ['active', 'deactive', 'requested'])->default('deactive')->after('edit_bank_details_status');
            $table->enum('edit_other_payment_method_description_status', ['active', 'deactive', 'requested'])->default('deactive')->after('edit_binance_mail_status');
            
            // Binance and Other Payment Methods
            $table->string('binance')->nullable()->after('edit_binance_mail_status');
            $table->text('other_payment_method_description')->nullable()->after('binance');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'address', 'balance', 'pay_method',
                'account_email', 'skype', 'company', 'website', 'promotion_description',
                'payoneer', 'paypal', 'aff_percent', 'sale_add', 'auto_renew',
                'sale_hide', 'status',
                 'default_affiliate_commission_1',
                'default_affiliate_commission_2',
                'default_affiliate_commission_3',
                'bank_details',
                'edit_paypal_mail_status',
                'edit_payoneer_mail_status',
                'edit_bank_details_status',
                'edit_binance_mail_status',
                'edit_other_payment_method_description_status',
                'binance',
                'other_payment_method_description'
            ]);
        });
    }
};