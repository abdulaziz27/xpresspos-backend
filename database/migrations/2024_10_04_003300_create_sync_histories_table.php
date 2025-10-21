<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('sync_type');
            $table->string('operation');
            $table->string('entity_type');
            $table->uuid('entity_id')->nullable();
            $table->json('payload');
            $table->json('conflicts')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->integer('priority')->default(0);
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'sync_type', 'status']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'priority', 'scheduled_at']);
            $table->index('batch_id');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
