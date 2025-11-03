<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL: Use ALTER TABLE MODIFY for enums
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method 
                ENUM('cash', 'credit_card', 'debit_card', 'qris', 'bank_transfer', 'e_wallet', 'pending')");
            
            DB::statement("ALTER TABLE payments MODIFY COLUMN status 
                ENUM('pending', 'completed', 'failed', 'cancelled', 'partial') DEFAULT 'pending'");
        } elseif ($driver === 'sqlite') {
            // SQLite: Enums are stored as TEXT, no need to modify
            // The model validation will handle the enum values
        }
        
        // Ensure received_amount column exists (add if missing)
        if (!Schema::hasColumn('payments', 'received_amount')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->decimal('received_amount', 12, 2)->default(0)->after('amount');
            });
        }
        
        // Ensure paid_at column exists (add if missing)
        if (!Schema::hasColumn('payments', 'paid_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->after('processed_at');
            });
        }
        
        // Add index for better query performance (with error handling)
        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['order_id', 'status'], 'idx_order_status');
            });
        } catch (\Exception $e) {
            // Index might already exist, ignore error
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Revert enums for MySQL
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method 
                ENUM('cash', 'credit_card', 'debit_card', 'qris', 'bank_transfer', 'e_wallet')");
            
            DB::statement("ALTER TABLE payments MODIFY COLUMN status 
                ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending'");
        }
        
        // Drop index (with error handling)
        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('idx_order_status');
            });
        } catch (\Exception $e) {
            // Index might not exist, ignore error
        }
        
        // Remove columns if they were added by this migration
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'received_amount')) {
                $table->dropColumn('received_amount');
            }
            if (Schema::hasColumn('payments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
    }
};
