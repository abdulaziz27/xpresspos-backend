<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('table_number');
            $table->string('name')->nullable();
            $table->integer('capacity')->default(4);
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance'])->default('available');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('qr_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('occupied_at')->nullable();
            $table->timestamp('last_cleared_at')->nullable();
            $table->uuid('current_order_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'is_active']);
            $table->unique(['store_id', 'table_number']);
            $table->index('occupied_at');
            $table->index('current_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
