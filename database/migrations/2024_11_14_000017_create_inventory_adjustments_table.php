<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('adjustment_number');
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->enum('reason', ['COUNT_DIFF', 'EXPIRED', 'DAMAGE', 'INITIAL'])->default('COUNT_DIFF');
            $table->dateTime('adjusted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->index(['store_id', 'status'], 'idx_adj_store_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
