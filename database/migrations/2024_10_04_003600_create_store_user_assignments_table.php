<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('assignment_role')->default('staff');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'user_id']);
            $table->index(['user_id', 'assignment_role']);
            $table->index(['store_id', 'assignment_role'], 'idx_store_assignments_role');
            $table->index(['user_id', 'store_id'], 'idx_user_store_assignments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_user_assignments');
    }
};
