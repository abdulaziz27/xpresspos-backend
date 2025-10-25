<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration removes Xendit-specific fields from the payments table
     * to maintain separation between store order payments and subscription payments.
     * Store order payments should not have Xendit integration.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Remove Xendit fields that were previously added
            if (Schema::hasColumn('payments', 'xendit_invoice_id')) {
                $table->dropIndex(['xendit_invoice_id']);
                $table->dropColumn('xendit_invoice_id');
            }
            
            if (Schema::hasColumn('payments', 'external_id')) {
                $table->dropIndex(['external_id']);
                $table->dropColumn('external_id');
            }
            
            // Remove invoice_id as it should only be in subscription_payments table
            if (Schema::hasColumn('payments', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropIndex(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Re-add the Xendit fields if rollback is needed.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Re-add fields if rollback is needed
            if (!Schema::hasColumn('payments', 'invoice_id')) {
                $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->after('payment_method_id');
                $table->index('invoice_id');
            }
            
            if (!Schema::hasColumn('payments', 'xendit_invoice_id')) {
                $table->string('xendit_invoice_id')->nullable()->after('gateway_response');
                $table->index('xendit_invoice_id');
            }
            
            if (!Schema::hasColumn('payments', 'external_id')) {
                $table->string('external_id')->nullable()->after('xendit_invoice_id');
                $table->index('external_id');
            }
        });
    }
};