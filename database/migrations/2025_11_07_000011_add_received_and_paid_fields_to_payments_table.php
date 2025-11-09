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
            // Check if received_amount column doesn't exist before adding
            if (!Schema::hasColumn('payments', 'received_amount')) {
                $table->decimal('received_amount', 12, 2)->nullable()->after('amount');
            }
            
            // Check if paid_at column doesn't exist before adding
            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('processed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Only drop columns if they exist
            if (Schema::hasColumn('payments', 'received_amount')) {
                $table->dropColumn('received_amount');
            }
            if (Schema::hasColumn('payments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
    }
};
