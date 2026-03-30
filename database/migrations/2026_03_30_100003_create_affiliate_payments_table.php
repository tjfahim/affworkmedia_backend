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
     Schema::create('affiliate_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aff_user_id');
            $table->string('title')->nullable();
            $table->string('email');
            $table->decimal('price', 10, 2);
            $table->string('pay_method');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('aff_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            // Indexes for better performance
            $table->index('aff_user_id');
            $table->index('status');
            $table->index('email');
            $table->index('created_at');
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_payments');
    }
};
