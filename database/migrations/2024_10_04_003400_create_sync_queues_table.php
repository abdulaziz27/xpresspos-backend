<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table is now redundant - functionality moved to sync_operations table
        // Schema::create('sync_queues', function (Blueprint $table) {
        //     // Moved to sync_operations table for better consolidation
        // });
    }

    public function down(): void
    {
        // Schema::dropIfExists('sync_queues'); // Table not created
    }
};
