<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates order_discounts table for tracking order-level discounts.
     */
    public function up(): void
    {
        Schema::create('order_discounts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('promotion_id', 36)->nullable();
            $table->string('voucher_id', 36)->nullable();
            $table->enum('discount_type', ['PROMOTION', 'VOUCHER', 'MANUAL']);
            $table->decimal('discount_amount', 18, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('set null');
            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('set null');

            // Indexes
            $table->index('order_id', 'idx_order_discounts_order');
            $table->index('promotion_id', 'idx_order_discounts_promotion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_discounts');
    }
};
