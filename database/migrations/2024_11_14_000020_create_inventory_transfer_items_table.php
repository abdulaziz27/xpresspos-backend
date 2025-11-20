<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates inventory_transfer_items table for transfer line items.
     */
    public function up(): void
    {
        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->string('uom_id', 36);
            $table->decimal('quantity_shipped', 18, 3);
            $table->decimal('quantity_received', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->timestamps();

            // Foreign keys
            $table->foreign('inventory_item_id', 'fk_transfer_items_inventory')
                  ->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('uom_id', 'fk_transfer_items_uom')
                  ->references('id')->on('uoms')->onDelete('restrict');

            // Index
            $table->index('inventory_transfer_id', 'idx_transfer_items_transfer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
    }
};
