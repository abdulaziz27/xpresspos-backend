<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * ✅ ENHANCEMENT: Tambah 'cancelled' ke payment_method enum
     * Tidak mengubah atau menghapus value yang sudah ada
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Tambah 'cancelled' ke enum (non-destructive)
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method 
                ENUM('cash', 'credit_card', 'debit_card', 'qris', 'bank_transfer', 'e_wallet', 'pending', 'cancelled')");
            
            Log::info('✅ Migration: Added "cancelled" to payment_method enum');
        } elseif ($driver === 'sqlite') {
            // SQLite: Enums are stored as TEXT, no need to modify
            // The model validation will handle the enum values
            Log::info('✅ Migration: SQLite detected, enum validation handled by model');
        }
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Update semua 'cancelled' jadi 'pending' dulu sebelum hapus dari enum
            DB::statement("UPDATE payments SET payment_method = 'pending' WHERE payment_method = 'cancelled'");
            
            // Hapus 'cancelled' dari enum
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method 
                ENUM('cash', 'credit_card', 'debit_card', 'qris', 'bank_transfer', 'e_wallet', 'pending')");
            
            Log::info('✅ Migration rollback: Removed "cancelled" from payment_method enum');
        }
    }
};
