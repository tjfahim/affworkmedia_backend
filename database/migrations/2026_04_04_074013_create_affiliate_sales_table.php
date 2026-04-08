<?php
// database/migrations/2026_04_04_000002_create_affiliate_sales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliateSalesTable extends Migration
{
    public function up()
    {
        Schema::create('affiliate_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('click_id')->constrained('affiliate_clicks')->onDelete('cascade');
            $table->foreignId('game_id')->nullable()->constrained('game_manages')->onDelete('set null');
            $table->foreignId('event_id')->nullable()->constrained('event_manages')->onDelete('set null');
            $table->string('package_type'); // basic, standard, premium or 5,10,15
            $table->decimal('package_price', 10, 2);
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_country')->nullable();
            $table->string('transaction_id')->unique();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->timestamp('purchased_at');
            $table->timestamps();
            
            $table->index(['affiliate_id', 'purchased_at']);
            $table->index('transaction_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('affiliate_sales');
    }
}