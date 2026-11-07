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
        Schema::create('pincodes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();
            $table->string('city');
            $table->string('state');
            $table->boolean('is_serviceable')->default(true);
            $table->boolean('cod_available')->default(true);
            $table->unsignedTinyInteger('delivery_days')->default(5);
            $table->timestamps();

            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pincodes');
    }
};
