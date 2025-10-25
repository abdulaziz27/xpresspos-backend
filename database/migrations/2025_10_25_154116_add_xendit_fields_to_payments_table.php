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
        Schema::table('payments', function (Blueprint $table) {
            // Check if columns don't exist before adding them
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'xendit_invoice_id')) {
                $table->dropIndex(['xendit_invoice_id']);
                $table->dropColumn('xendit_invoice_id');
            }
            
            if (Schema::hasColumn('payments', 'external_id')) {
                $table->dropIndex(['external_id']);
                $table->dropColumn('external_id');
            }
        });
    }
};
