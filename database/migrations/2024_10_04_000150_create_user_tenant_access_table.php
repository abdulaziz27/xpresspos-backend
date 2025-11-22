<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Domain A2: User-Tenant Access Control
     * Creates M:N relationship between users and tenants with role-based access.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_tenant_access')) {
            Schema::create('user_tenant_access', function (Blueprint $table) {
                $table->string('id', 36)->primary()->comment('UUID primary key');
                $table->unsignedBigInteger('user_id');
                $table->string('tenant_id', 36);
                $table->string('role')->default('owner')->comment('owner/admin/accountant/viewer');
                $table->timestamps();

                // Foreign keys
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

                // Unique constraint: one role per user per tenant
                $table->unique(['user_id', 'tenant_id'], 'uk_user_tenant_access');

                // Index for tenant-scoped queries
                $table->index(['tenant_id', 'role'], 'idx_user_tenant_access_tenant_role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tenant_access');
    }
};
