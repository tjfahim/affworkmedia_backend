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
        Schema::create('event_manages', function (Blueprint $table) {
            $table->id();
             $table->unsignedBigInteger('game_manage_id');
            $table->unsignedBigInteger('first_team_id');
            $table->unsignedBigInteger('second_team_id');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->enum('status', ['upcoming', 'running', 'finished'])->default('upcoming');
            $table->timestamps();

            // Foreign keys
            $table->foreign('game_manage_id')->references('id')->on('game_manages')->onDelete('cascade');
            $table->foreign('first_team_id')->references('id')->on('team_manages')->onDelete('cascade');
            $table->foreign('second_team_id')->references('id')->on('team_manages')->onDelete('cascade');

            // Indexes
            $table->index('start_datetime');
            $table->index('status');
            $table->index('game_manage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_manages');
    }
};
