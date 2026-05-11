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
        Schema::create('relief_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('citizen_id');
            $table->unsignedBigInteger('coordinator_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('disaster_type', ['lu_lut', 'sat_lo', 'bao', 'chay', 'khac']);
            $table->integer('urgency_level');
            $table->integer('people_count')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->text('address')->nullable();
            $table->json('required_skills')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('citizen_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('coordinator_id')->references('id')->on('users')->onDelete('set null');
            $table->index('citizen_id');
            $table->index('coordinator_id');
            $table->index('status');
            $table->index('urgency_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relief_requests');
    }
};
