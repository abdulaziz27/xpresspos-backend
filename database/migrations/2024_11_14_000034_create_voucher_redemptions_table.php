<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates voucher_redemptions table for tracking voucher usage.
     */
    public function up(): void
    {
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('voucher_id', 36);
            $table->foreignUuid('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->dateTime('redeemed_at');
            $table->decimal('discount_amount', 18, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('cascade');

            // Index
            $table->index(['voucher_id', 'member_id'], 'idx_voucher_redemptions_voucher_member');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
    }
};
