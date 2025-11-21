<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->integer('current_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('available_stock')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->decimal('average_cost', 10, 2)->default(0);
            $table->decimal('total_value', 10, 2)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'product_id']);
            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'current_stock']);
            $table->index(['store_id', 'available_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
