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
            $table->integer('default_sale_hide')->default(0);
            $table->integer('default_affiliate_commission_1')->default(0);
            $table->integer('default_affiliate_commission_2')->default(0);
            $table->integer('default_affiliate_commission_3')->default(0);
            $table->string('default_master_password')->nullable();
            $table->string('default_payment_mail')->nullable();
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
