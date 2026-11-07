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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable();
            $table->string('full_name');
            $table->string('phone', 15);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('pincode', 6);
            $table->string('country', 2)->default('IN');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
