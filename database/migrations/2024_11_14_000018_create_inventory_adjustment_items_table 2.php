<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->decimal('system_qty', 18, 3);
            $table->decimal('counted_qty', 18, 3);
            $table->decimal('difference_qty', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 2);
            $table->timestamps();

            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');

            $table->index('inventory_adjustment_id', 'idx_adj_items_adj');
            $table->index('inventory_item_id', 'idx_adj_items_inv_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
    }
};
