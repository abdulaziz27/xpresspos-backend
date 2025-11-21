<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates promotion_rewards table for flexible promotion rewards.
     */
    public function up(): void
    {
        Schema::create('promotion_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('promotion_id', 36);
            $table->enum('reward_type', [
                'PCT_OFF',
                'AMOUNT_OFF',
                'BUY_X_GET_Y',
                'POINTS_MULTIPLIER'
            ]);
            $table->json('reward_value');
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
        Schema::dropIfExists('promotion_rewards');
    }
};
