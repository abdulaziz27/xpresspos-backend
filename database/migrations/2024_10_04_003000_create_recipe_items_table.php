<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_product_id')->constrained('products');
            $table->decimal('quantity', 10, 3);
            $table->string('unit');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'recipe_id']);
            $table->index('ingredient_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
