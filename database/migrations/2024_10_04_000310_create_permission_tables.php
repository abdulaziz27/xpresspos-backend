<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        throw_if(empty($tableNames), new \Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.'));
        throw_if($teams && empty($columnNames['team_foreign_key'] ?? null), new \Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.'));

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $columnNames) {
            $table->bigIncrements('id');
            if ($teams || config('permission.testing')) {
                // Use char(36) for UUID store_id instead of unsignedBigInteger
                $table->char('store_id', 36)->nullable();
                $table->index('store_id', 'roles_store_id_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique(['store_id', 'name', 'guard_name']);
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            if ($teams) {
                // Use char(36) for UUID store_id instead of unsignedBigInteger
                $table->char('store_id', 36)->nullable();
                $table->index('store_id', 'model_has_permissions_store_id_index');
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');

                // Add performance index for permission lookup
                $table->index(['model_id', 'model_type', 'store_id'], 'idx_model_permissions_lookup');

                // Create unique index instead of primary key since store_id is nullable
                $table->unique([
                    $pivotPermission,
                    $columnNames['model_morph_key'],
                    'model_type',
                    'store_id',
                ], 'model_has_permissions_permission_model_type_unique');
            } else {
                $table->primary([
                    $pivotPermission,
                    $columnNames['model_morph_key'],
                    'model_type',
                ], 'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            if ($teams) {
                // Use char(36) for UUID store_id instead of unsignedBigInteger
                $table->char('store_id', 36)->nullable();
                $table->index('store_id', 'model_has_roles_store_id_index');
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');

                // Add performance index for role lookup
                $table->index(['model_id', 'model_type', 'store_id'], 'idx_model_roles_lookup');

                // Create unique index instead of primary key since store_id is nullable
                $table->unique([
                    $pivotRole,
                    $columnNames['model_morph_key'],
                    'model_type',
                    'store_id',
                ], 'model_has_roles_role_model_type_unique');
            } else {
                $table->primary([
                    $pivotRole,
                    $columnNames['model_morph_key'],
                    'model_type',
                ], 'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        // Create permission audit logs table
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
            $table->index(['store_id', 'created_at'], 'idx_audit_store_date');
            $table->index(['user_id', 'store_id', 'created_at'], 'idx_audit_user_store_date');
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::dropIfExists('permission_audit_logs');
        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
