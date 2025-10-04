<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cogs_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->integer('quantity_sold');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cogs', 10, 2);
            $table->enum('calculation_method', ['fifo', 'lifo', 'weighted_average']);
            $table->json('cost_breakdown')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_id']);
            $table->index(['store_id', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cogs_history');
    }
};
