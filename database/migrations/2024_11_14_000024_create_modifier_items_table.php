<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates modifier_items table with denormalized store_id for POS performance.
     */
    public function up(): void
    {
        Schema::create('modifier_items', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('modifier_group_id', 36);
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_delta', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('modifier_group_id')->references('id')->on('modifier_groups')->onDelete('cascade');

            // Indexes for POS UI optimization
            $table->index(['tenant_id', 'is_active'], 'idx_mod_items_tenant_active');
            $table->index(['modifier_group_id', 'is_active'], 'idx_mod_items_group_active');
            $table->index(['modifier_group_id', 'sort_order'], 'idx_mod_items_group_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_items');
    }
};
