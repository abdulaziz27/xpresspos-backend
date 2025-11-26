<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('add_on_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relationships
            $table->unsignedBigInteger('tenant_add_on_id');
            $table->foreign('tenant_add_on_id')->references('id')->on('tenant_add_ons')->cascadeOnDelete();
            
            // Xendit integration fields
            $table->string('xendit_invoice_id')->unique();
            $table->string('external_id')->unique();
            
            // Payment details
            $table->enum('payment_method', ['bank_transfer', 'e_wallet', 'qris', 'credit_card'])->nullable();
            $table->string('payment_channel')->nullable(); // BCA, OVO, DANA, etc
            $table->decimal('amount', 15, 2);
            $table->decimal('gateway_fee', 8, 2)->default(0);
            
            // Status and tracking
            $table->enum('status', ['pending', 'paid', 'expired', 'failed'])->default('pending');
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('tenant_add_on_id');
            $table->index('xendit_invoice_id');
            $table->index('external_id');
            $table->index('status');
            $table->index('paid_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_on_payments');
    }
};
