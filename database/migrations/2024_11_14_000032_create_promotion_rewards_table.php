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
            $table->string('promotion_id', 36);
            $table->enum('reward_type', [
                'PCT_OFF',
                'AMOUNT_OFF',
                'BUY_X_GET_Y',
                'POINTS_MULTIPLIER'
            ]);
            $table->json('reward_value');
            $table->timestamps();

            // Foreign key
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
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
