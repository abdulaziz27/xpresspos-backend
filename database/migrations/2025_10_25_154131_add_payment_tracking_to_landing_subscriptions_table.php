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
        Schema::table('landing_subscriptions', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('landing_subscriptions', 'xendit_invoice_id')) {
                $table->string('xendit_invoice_id')->nullable()->after('onboarding_url');
                $table->index('xendit_invoice_id');
            }
            
            if (!Schema::hasColumn('landing_subscriptions', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'paid', 'expired', 'failed'])->default('pending')->after('xendit_invoice_id');
                $table->index('payment_status');
            }
            
            if (!Schema::hasColumn('landing_subscriptions', 'payment_amount')) {
                $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_status');
            }
            
            if (!Schema::hasColumn('landing_subscriptions', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_amount');
                $table->index('paid_at');
            }
            
            if (!Schema::hasColumn('landing_subscriptions', 'subscription_id')) {
                $table->uuid('subscription_id')->nullable()->after('paid_at');
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('landing_subscriptions', 'subscription_id')) {
                $table->dropForeign(['subscription_id']);
                $table->dropColumn('subscription_id');
            }
            
            if (Schema::hasColumn('landing_subscriptions', 'paid_at')) {
                $table->dropIndex(['paid_at']);
                $table->dropColumn('paid_at');
            }
            
            if (Schema::hasColumn('landing_subscriptions', 'payment_amount')) {
                $table->dropColumn('payment_amount');
            }
            
            if (Schema::hasColumn('landing_subscriptions', 'payment_status')) {
                $table->dropIndex(['payment_status']);
                $table->dropColumn('payment_status');
            }
            
            if (Schema::hasColumn('landing_subscriptions', 'xendit_invoice_id')) {
                $table->dropIndex(['xendit_invoice_id']);
                $table->dropColumn('xendit_invoice_id');
            }
        });
    }
};
