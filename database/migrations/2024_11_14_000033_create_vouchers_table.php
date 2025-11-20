<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates vouchers table for voucher management.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->string('promotion_id', 36)->nullable();
            $table->string('code')->unique();
            $table->integer('max_redemptions')->nullable(); // Nullable for unlimited
            $table->integer('redemptions_count')->default(0);
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('set null');

            // Index
            $table->index(['tenant_id', 'status'], 'idx_vouchers_tenant_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
