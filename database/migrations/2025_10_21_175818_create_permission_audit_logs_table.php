<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('store_id', 36);
            $table->unsignedBigInteger('user_id'); // User yang permissions-nya diubah
            $table->unsignedBigInteger('changed_by'); // User yang melakukan perubahan
            $table->string('action'); // 'granted', 'revoked', 'role_changed', 'reset_to_default'
            $table->string('permission')->nullable(); // Permission yang diubah (null untuk role changes)
            $table->string('old_value')->nullable(); // Role lama atau permission sebelumnya
            $table->string('new_value')->nullable(); // Role baru atau permission baru
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['store_id', 'user_id']);
            $table->index(['changed_by']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_audit_logs');
    }
};