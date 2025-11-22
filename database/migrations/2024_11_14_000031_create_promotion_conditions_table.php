<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates promotion_conditions table for flexible promotion rules.
     */
    public function up(): void
    {
        Schema::create('promotion_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('promotion_id', 36);
            $table->enum('condition_type', [
                'MIN_SPEND',
                'ITEM_INCLUDE',
                'CUSTOMER_TIER_IN',
                'DOW',
                'TIME_RANGE',
                'BRANCH_IN',
                'NEW_CUSTOMER'
            ]);
            $table->json('condition_value');
            $table->timestamps();

            // Foreign keys
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
            
            // Indexes
            $table->index(['tenant_id', 'promotion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_conditions');
    }
};
