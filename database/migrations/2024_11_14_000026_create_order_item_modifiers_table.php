<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates order_item_modifiers table to track modifiers in orders.
     */
    public function up(): void
    {
        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('modifier_item_id', 36);
            $table->integer('quantity')->default(1);
            $table->decimal('price_delta', 18, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('modifier_item_id')->references('id')->on('modifier_items')->onDelete('cascade');

            // Index
            $table->index('order_item_id', 'idx_order_item_mods_order_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');
    }
};
