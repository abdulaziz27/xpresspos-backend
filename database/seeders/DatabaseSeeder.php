<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create plans first
        $this->call([
            PlanSeeder::class,
        ]);

        // Create roles and permissions
        $this->call([
            RoleSeeder::class,
        ]);

        // Create users
        $this->call([
            UserSeeder::class,
        ]);

        // Create stores and subscriptions
        $this->call([
            StoreSeeder::class,
        ]);

        // Then seed roles and permissions (after stores are created)
        $this->call([
            PermissionsAndRolesSeeder::class,
        ]);

        $storeId = config('demo.store_id');

        // Create manager user
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@xpresspos.id'],
            [
                'name' => 'Store Manager',
                'password' => Hash::make('password123'),
                'store_id' => $storeId,
                'email_verified_at' => now(),
            ]
        );
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')
            ->where('store_id', $storeId)
            ->first();
        if ($managerRole) {
            // CRITICAL: Always set team context BEFORE any role operation
            setPermissionsTeamId($storeId);
            
            // Force remove any existing role assignments for this user in this store
            $managerUser->roles()->wherePivot('store_id', $storeId)->detach();
            
            // Assign role fresh
            $managerUser->assignRole($managerRole);
            
            // Verify assignment
            $managerUser->refresh();
            setPermissionsTeamId($storeId);
        }
        $this->assignUserToStore($managerUser, $storeId, 'manager');

        // Create staff user
        $staffUser = User::firstOrCreate(
            ['email' => 'staff@xpresspos.id'],
            [
                'name' => 'Store Staff',
                'password' => Hash::make('password123'),
                'store_id' => $storeId,
                'email_verified_at' => now(),
            ]
        );
        $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')
            ->where('store_id', $storeId)
            ->first();
        if ($staffRole) {
            // CRITICAL: Always set team context BEFORE any role operation
            setPermissionsTeamId($storeId);
            
            // Force remove any existing role assignments for this user in this store
            $staffUser->roles()->wherePivot('store_id', $storeId)->detach();
            
            // Assign role fresh
            $staffUser->assignRole($staffRole);
            
            // Verify assignment
            $staffUser->refresh();
            setPermissionsTeamId($storeId);
        }
        $this->assignUserToStore($staffUser, $storeId, 'staff');

        // Create Filament users (admin and owner)
        $this->call([
            FilamentUserSeeder::class,
        ]);
        
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            DiscountSeeder::class,
            OwnerDemoSeeder::class,
            OwnerPanelSeeder::class,
        ]);
    }

    private function assignUserToStore(User $user, ?string $storeId, string $role, bool $isPrimary = false): void
    {
        if (!$storeId) {
            return;
        }

        StoreUserAssignment::updateOrCreate(
            [
                'store_id' => $storeId,
                'user_id' => $user->id,
            ],
            [
                'assignment_role' => $role,
                'is_primary' => $isPrimary,
            ]
        );
    }
}
