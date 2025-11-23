<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates order_item_discounts table for tracking item-level discounts.
     */
    public function up(): void
    {
        Schema::create('order_item_discounts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('promotion_id', 36)->nullable();
            $table->enum('discount_type', ['PROMOTION', 'MANUAL']);
            $table->decimal('discount_amount', 18, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('set null');

            // Index
            $table->index('order_item_id', 'idx_order_item_discounts_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_discounts');
    }
};
