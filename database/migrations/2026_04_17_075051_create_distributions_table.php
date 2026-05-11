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
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('rescue_team_id')->nullable();
            $table->unsignedBigInteger('coordinator_id');
            $table->json('items_detail');
            $table->decimal('total_value', 15, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'delivering', 'delivered'])->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('request_id')->references('id')->on('relief_requests')->onDelete('cascade');
            $table->foreign('rescue_team_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('coordinator_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('warehouse_id');
            $table->index('request_id');
            $table->index('rescue_team_id');
            $table->index('coordinator_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributions');
    }
};
