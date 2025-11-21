<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_occupancy_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('table_id')->constrained('tables')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('occupied_at');
            $table->timestamp('cleared_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('party_size')->nullable();
            $table->decimal('order_total', 12, 2)->nullable();
            $table->enum('status', ['occupied', 'cleared', 'abandoned'])->default('occupied');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'occupied_at']);
            $table->index(['table_id', 'occupied_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_occupancy_histories');
    }
};
