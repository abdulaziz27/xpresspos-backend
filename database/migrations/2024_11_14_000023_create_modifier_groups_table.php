<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates modifier_groups table for product modifiers (e.g., Size, Toppings).
     */
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'is_active'], 'idx_mod_groups_tenant_active');
            $table->index(['tenant_id', 'sort_order'], 'idx_mod_groups_tenant_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_groups');
    }
};
