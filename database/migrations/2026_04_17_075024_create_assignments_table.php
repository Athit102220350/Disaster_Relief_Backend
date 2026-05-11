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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('rescue_team_id');
            $table->enum('algorithm', ['Hungarian', 'Greedy', 'GeneticAlgorithm']);
            $table->float('cost_score')->nullable();
            $table->float('distance_km')->nullable();
            $table->json('route_data')->nullable();
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'cancelled'])->default('assigned');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreign('request_id')->references('id')->on('relief_requests')->onDelete('cascade');
            $table->foreign('rescue_team_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('request_id');
            $table->index('rescue_team_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
