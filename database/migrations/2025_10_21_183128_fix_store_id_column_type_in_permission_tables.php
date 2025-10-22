<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix store_id column type to match UUID stores
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex('roles_team_foreign_key_index');
            $table->dropColumn('store_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->char('store_id', 36)->nullable()->after('id');
            $table->index('store_id', 'roles_store_id_index');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex('model_has_roles_team_foreign_key_index');
            $table->dropColumn('store_id');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->char('store_id', 36)->nullable()->after('role_id');
            $table->index('store_id', 'model_has_roles_store_id_index');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropIndex('model_has_permissions_team_foreign_key_index');
            $table->dropColumn('store_id');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->char('store_id', 36)->nullable()->after('permission_id');
            $table->index('store_id', 'model_has_permissions_store_id_index');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Revert back to original structure
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex('model_has_permissions_store_id_index');
            $table->dropColumn('store_id');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('permission_id');
            $table->index('store_id', 'model_has_permissions_team_foreign_key_index');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex('model_has_roles_store_id_index');
            $table->dropColumn('store_id');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('role_id');
            $table->index('store_id', 'model_has_roles_team_foreign_key_index');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex('roles_store_id_index');
            $table->dropColumn('store_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
            $table->index('store_id', 'roles_team_foreign_key_index');
        });
    }
};