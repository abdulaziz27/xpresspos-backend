<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates subscription_payments table for handling subscription billing via Xendit.
     * This is separate from the payments table which handles store order transactions.
     */
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relationships
            $table->unsignedBigInteger('landing_subscription_id')->nullable();
            $table->uuid('subscription_id')->nullable();
            $table->uuid('invoice_id')->nullable();
            
            // Xendit integration fields
            $table->string('xendit_invoice_id')->unique();
            $table->string('external_id')->unique();
            
            // Payment details
            $table->enum('payment_method', ['bank_transfer', 'e_wallet', 'qris', 'credit_card']);
            $table->string('payment_channel')->nullable(); // BCA, OVO, DANA, etc
            $table->decimal('amount', 15, 2);
            $table->decimal('gateway_fee', 8, 2)->default(0);
            
            // Status and tracking
            $table->enum('status', ['pending', 'paid', 'expired', 'failed'])->default('pending');
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('landing_subscription_id')->references('id')->on('landing_subscriptions')->onDelete('set null');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            
            // Indexes for performance
            $table->index('landing_subscription_id');
            $table->index('subscription_id');
            $table->index('invoice_id');
            $table->index('xendit_invoice_id');
            $table->index('external_id');
            $table->index('status');
            $table->index('payment_method');
            $table->index('paid_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};