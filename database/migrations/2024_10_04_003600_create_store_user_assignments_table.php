<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        });

        // Seed assignment data from existing users.store_id if available
        $users = DB::table('users')
            ->select('id', 'store_id')
            ->whereNotNull('store_id')
            ->get();

        $now = now();
        $roleMap = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', $users->pluck('id'))
            ->get()
            ->groupBy('model_id');

        foreach ($users as $user) {
            // Skip if assignment already exists (shouldn't happen on fresh table)
            $roleName = optional($roleMap->get($user->id))[0]->name ?? 'staff';

            DB::table('store_user_assignments')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'assignment_role' => $roleName,
                'is_primary' => in_array($roleName, ['owner', 'admin_sistem']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_user_assignments');
    }
};
