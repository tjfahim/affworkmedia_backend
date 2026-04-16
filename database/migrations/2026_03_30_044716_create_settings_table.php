<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('landerpage_domain')->nullable();
            $table->string('player_page_domain')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('manager_email')->nullable();
            $table->string('manager_telegram')->nullable();
            $table->string('manager_microsoft')->nullable();
            $table->integer('default_sale_hide')->default(3);
            $table->integer('default_affiliate_commission_1')->default(70);
            $table->integer('default_affiliate_commission_2')->default(50);
            $table->integer('default_affiliate_commission_3')->default(40);
            $table->string('default_master_password')->nullable()->default('123456789');
            $table->string('default_payment_mail')->nullable();
            $table->boolean('is_paypal_active')->default(false);
            $table->boolean('is_payoneer_active')->default(false);
            $table->boolean('is_bank_transfer_active')->default(true);
            $table->boolean('is_binance_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
