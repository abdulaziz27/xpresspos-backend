<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates cogs_details table for granular COGS tracking.
     */
    public function up(): void
    {
        Schema::create('cogs_details', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignUuid('cogs_history_id')->constrained('cogs_history')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('inventory_item_id', 36);
            $table->string('lot_id', 36)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('lot_id')->references('id')->on('inventory_lots')->onDelete('set null');

            // Indexes
            $table->index('cogs_history_id', 'idx_cogs_details_history');
            $table->index('inventory_item_id', 'idx_cogs_details_inv_item');
            $table->index('order_item_id', 'idx_cogs_details_order_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cogs_details');
    }
};
