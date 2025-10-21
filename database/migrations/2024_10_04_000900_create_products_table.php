<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('track_inventory')->default(false);
            $table->json('variants')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'category_id']);
            $table->index('track_inventory');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
