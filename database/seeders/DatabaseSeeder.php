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
        $store = \App\Models\Store::find($storeId);
        $tenantId = $store?->tenant_id;

        if (!$storeId || !$tenantId) {
            $this->command->warn('⚠️ No store or tenant found. Skipping manager and staff role assignment.');
            return;
        }

        // Create manager user
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@xpresspos.id'],
            [
                'name' => 'Store Manager',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')
            ->where('tenant_id', $tenantId)
            ->first();
        if ($managerRole) {
            // CRITICAL: Always set team context BEFORE any role operation
            setPermissionsTeamId($tenantId);
            
            // Force remove any existing role assignments for this user in this tenant
            $managerUser->roles()->wherePivot('tenant_id', $tenantId)->detach();
            
            // Assign role fresh
            $managerUser->assignRole($managerRole);
            
            // Verify assignment
            $managerUser->refresh();
            setPermissionsTeamId($tenantId);
        }
        $this->assignUserToStore($managerUser, $storeId, 'manager');

        // Create staff user
        $staffUser = User::firstOrCreate(
            ['email' => 'staff@xpresspos.id'],
            [
                'name' => 'Store Staff',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')
            ->where('tenant_id', $tenantId)
            ->first();
        if ($staffRole) {
            // CRITICAL: Always set team context BEFORE any role operation
            setPermissionsTeamId($tenantId);
            
            // Force remove any existing role assignments for this user in this tenant
            $staffUser->roles()->wherePivot('tenant_id', $tenantId)->detach();
            
            // Assign role fresh
            $staffUser->assignRole($staffRole);
            
            // Verify assignment
            $staffUser->refresh();
            setPermissionsTeamId($tenantId);
        }
        $this->assignUserToStore($staffUser, $storeId, 'staff');

        // Seed UOMs first (required for inventory items, recipes, etc.)
        $this->call([
            UomSeeder::class,
        ]);

        // Create Filament users (admin and owner)
        $this->call([
            FilamentUserSeeder::class,
        ]);
        
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            DiscountSeeder::class,
            MemberTierSeeder::class,
            PromotionSeeder::class,
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
