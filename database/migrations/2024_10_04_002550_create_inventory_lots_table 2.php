<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates inventory_lots table for lot/batch tracking.
     */
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->string('lot_code');
            $table->date('mfg_date')->nullable();
            $table->date('exp_date')->nullable();
            $table->decimal('initial_qty', 18, 3);
            $table->decimal('remaining_qty', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->enum('status', ['active', 'expired', 'depleted'])->default('active');
            $table->timestamps();

            // Foreign keys
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('cascade');

            // Indexes
            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'inventory_item_id'], 'idx_lots_store_item');
            $table->index(['store_id', 'exp_date'], 'idx_lots_store_exp');
            $table->index(['store_id', 'inventory_item_id', 'status'], 'idx_lots_store_item_status');

            // Unique constraint
            $table->unique(['store_id', 'lot_code'], 'uk_lots_store_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
    }
};
