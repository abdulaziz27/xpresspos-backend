<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes for better permission checking performance
        // Note: model_has_permissions doesn't have store_id column, only model_has_roles does
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->index(['model_id', 'model_type'], 'idx_model_permissions_lookup');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->index(['model_id', 'model_type', 'store_id'], 'idx_model_roles_lookup');
        });

        Schema::table('store_user_assignments', function (Blueprint $table) {
            $table->index(['store_id', 'assignment_role'], 'idx_store_assignments_role');
            $table->index(['user_id', 'store_id'], 'idx_user_store_assignments');
        });

        // Add composite index for permission audit logs
        Schema::table('permission_audit_logs', function (Blueprint $table) {
            $table->index(['store_id', 'created_at'], 'idx_audit_store_date');
            $table->index(['user_id', 'store_id', 'created_at'], 'idx_audit_user_store_date');
        });
    }

    public function down(): void
    {
        Schema::table('permission_audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_store_date');
            $table->dropIndex('idx_audit_user_store_date');
        });

        Schema::table('store_user_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_store_assignments_role');
            $table->dropIndex('idx_user_store_assignments');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex('idx_model_roles_lookup');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_model_permissions_lookup');
        });
    }
};