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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('method', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('gateway_signature')->nullable();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 3)->default('INR');
            $table->string('failure_reason')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
