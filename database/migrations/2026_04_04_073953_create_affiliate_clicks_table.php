<?php
// database/migrations/2026_04_04_000001_create_affiliate_clicks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliateClicksTable extends Migration
{
    public function up()
    {
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('game_id')->nullable()->constrained('game_manages')->onDelete('set null');
            $table->foreignId('event_id')->nullable()->constrained('event_manages')->onDelete('set null');
            $table->string('ip_address');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('browser')->nullable();
            $table->string('sub1')->nullable();
            $table->string('sub2')->nullable();
            $table->string('sub3')->nullable();
            $table->string('sub4')->nullable();
            $table->string('sub5')->nullable();
            $table->string('sub6')->nullable();
            $table->string('referrer')->nullable();
            $table->string('session_id')->nullable();
            $table->boolean('is_unique')->default(true);
             $table->string('fingerprint')->nullable();
             $table->timestamps();
             
             $table->index(['affiliate_id', 'created_at']);
             $table->index('fingerprint');
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('affiliate_clicks');
    }
}