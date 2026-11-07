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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('simple');
            $table->string('status', 20)->default('draft');
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('hsn_code', 16)->nullable();
            $table->unsignedTinyInteger('gst_rate')->default(18);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('deal_ends_at')->nullable();
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('category_id');
            $table->index('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
