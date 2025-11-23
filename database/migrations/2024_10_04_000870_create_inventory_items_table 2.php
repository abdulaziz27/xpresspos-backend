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
        if (!Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('tenant_id', 36);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->string('name', 255);
                $table->string('sku', 100)->nullable();
                $table->string('category', 100)->nullable();
                $table->string('uom_id', 36);
                $table->boolean('track_lot')->default(false);
                $table->boolean('track_stock')->default(true);
                $table->decimal('min_stock_level', 18, 3)->default(0);
                $table->decimal('default_cost', 18, 4)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('uom_id')->references('id')->on('uoms')->onDelete('restrict');
                
                // Indexes for performance
                $table->index(['tenant_id', 'status'], 'idx_inv_items_tenant_status');
                $table->index(['tenant_id', 'name'], 'idx_inv_items_tenant_name');
                
                // Unique constraint: SKU must be unique per tenant (if provided)
                $table->unique(['tenant_id', 'sku'], 'uk_inv_items_tenant_sku');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
