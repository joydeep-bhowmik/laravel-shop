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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('razorpay');
            $table->string('event_id')->unique();
            $table->string('event', 60);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
