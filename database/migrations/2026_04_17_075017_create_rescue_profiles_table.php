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
        Schema::create('rescue_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('specialization', 100)->nullable();
            $table->text('certificate')->nullable();
            $table->string('organization', 150)->nullable();
            $table->enum('status', ['available', 'busy', 'offline'])->default('offline');
            $table->enum('vehicle_type', ['xe_may', 'o_to', 'thuyen', 'truc_thang'])->nullable();
            $table->integer('total_missions')->default(0);
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rescue_profiles');
    }
};
