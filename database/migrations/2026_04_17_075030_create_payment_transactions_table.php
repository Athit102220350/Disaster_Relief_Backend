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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('donation_id');
            $table->string('bank_transaction_id', 100)->nullable();
            $table->enum('payment_gateway', ['VNPay', 'Momo', 'bank_transfer'])->nullable();
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->string('transfer_content', 200)->nullable();
            $table->timestamp('transacted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->foreign('donation_id')->references('id')->on('donations')->onDelete('cascade');
            $table->index('donation_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
