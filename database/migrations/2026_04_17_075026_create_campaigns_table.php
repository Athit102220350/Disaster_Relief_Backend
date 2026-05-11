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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coordinator_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->enum('status', ['open', 'closed', 'completed'])->default('open');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->foreign('coordinator_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('coordinator_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
