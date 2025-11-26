<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE tenant_add_ons
            MODIFY COLUMN status ENUM('pending', 'active', 'cancelled', 'expired')
            NOT NULL DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE tenant_add_ons
            MODIFY COLUMN status ENUM('active', 'cancelled', 'expired')
            NOT NULL DEFAULT 'active'
        ");
    }
};

