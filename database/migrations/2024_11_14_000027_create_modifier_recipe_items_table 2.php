<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates modifier_recipe_items table for modifier inventory tracking.
     */
    public function up(): void
    {
        Schema::create('modifier_recipe_items', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('modifier_item_id', 36);
            $table->string('inventory_item_id', 36);
            $table->string('uom_id', 36);
            $table->decimal('quantity', 18, 3);
            $table->timestamps();

            // Foreign keys
            $table->foreign('modifier_item_id')->references('id')->on('modifier_items')->onDelete('cascade');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('uom_id')->references('id')->on('uoms')->onDelete('restrict');

            // Indexes
            $table->index('modifier_item_id', 'idx_mod_recipe_items_mod_item');
            $table->index('inventory_item_id', 'idx_mod_recipe_items_inv_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_recipe_items');
    }
};
