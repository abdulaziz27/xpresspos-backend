<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates inventory_transfers table for inter-store transfers.
     */
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('from_store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('to_store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('transfer_number');
            $table->enum('status', ['draft', 'approved', 'shipped', 'received', 'cancelled']);
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'from_store_id']);
            $table->index(['tenant_id', 'to_store_id']);
            $table->index(['from_store_id', 'status'], 'idx_transfers_from_status');
            $table->index(['to_store_id', 'status'], 'idx_transfers_to_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
