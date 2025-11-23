<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates product_modifier_groups table for M:N relationship.
     */
    public function up(): void
    {
        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('modifier_group_id', 36);
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('modifier_group_id')->references('id')->on('modifier_groups')->onDelete('cascade');

            // Unique constraint
            $table->unique(['product_id', 'modifier_group_id'], 'uk_prod_mod_group');

            // Index
            $table->index('product_id', 'idx_prod_mod_groups_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_modifier_groups');
    }
};
