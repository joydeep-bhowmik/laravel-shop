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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('payment_status', 20)->default('pending');
            $table->string('payment_method', 20);
            $table->string('coupon_code')->nullable();
            $table->string('shipping_method', 40)->default('standard');
            $table->string('shipping_state', 60);
            $table->json('shipping_address');
            $table->unsignedBigInteger('subtotal_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->unsignedBigInteger('shipping_paise')->default(0);
            $table->unsignedBigInteger('cgst_paise')->default(0);
            $table->unsignedBigInteger('sgst_paise')->default(0);
            $table->unsignedBigInteger('igst_paise')->default(0);
            $table->unsignedBigInteger('total_paise');
            $table->string('tracking_number')->nullable();
            $table->string('courier')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'placed_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
