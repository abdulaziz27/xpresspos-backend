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

        // Create stores and subscriptions
        $this->call([
            StoreSeeder::class,
        ]);

        // Then seed roles and permissions (after stores are created)
        $this->call([
            PermissionsAndRolesSeeder::class,
        ]);

        $storeId = config('demo.store_id');

        // Create a system admin user (skip role assignment for now)
        $systemAdmin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@posxpress.com',
            'password' => Hash::make('password'),
            'store_id' => null, // System admin doesn't belong to any store
        ]);
        // Note: admin_sistem role will be assigned manually or through different mechanism

        // Create owner users for Filament login
        $ownerUser = User::factory()->create([
            'name' => 'Abdul Aziz',
            'email' => 'aziz@xpress.com',
            'password' => Hash::make('password'),
            'store_id' => $storeId,
        ]);
        // Find the owner role for this specific store
        $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
            ->where('store_id', $storeId)
            ->first();
        if ($ownerRole) {
            setPermissionsTeamId($storeId);
            $ownerUser->assignRole($ownerRole);
        }
        $this->assignUserToStore($ownerUser, $storeId, 'owner', isPrimary: true);

        // Create additional owner user for easy testing
        $testOwner = User::factory()->create([
            'name' => 'Test Owner',
            'email' => 'owner@test.com',
            'password' => Hash::make('password'),
            'store_id' => $storeId,
        ]);
        if ($ownerRole) {
            setPermissionsTeamId($storeId);
            $testOwner->assignRole($ownerRole);
        }
        $this->assignUserToStore($testOwner, $storeId, 'owner', isPrimary: false);

        // Create test users with different roles and easy credentials
        
        // Create manager user for testing
        $managerUser = User::factory()->create([
            'name' => 'Test Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'store_id' => $storeId,
        ]);
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')
            ->where('store_id', $storeId)
            ->first();
        if ($managerRole) {
            setPermissionsTeamId($storeId);
            $managerUser->assignRole($managerRole);
        }
        $this->assignUserToStore($managerUser, $storeId, 'manager');

        // Create staff user for testing
        $staffUser = User::factory()->create([
            'name' => 'Test Staff',
            'email' => 'staff@test.com',
            'password' => Hash::make('password'),
            'store_id' => $storeId,
        ]);
        $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')
            ->where('store_id', $storeId)
            ->first();
        if ($staffRole) {
            setPermissionsTeamId($storeId);
            $staffUser->assignRole($staffRole);
        }
        $this->assignUserToStore($staffUser, $storeId, 'staff');

        // Create additional random users
        User::factory(2)->create([
            'store_id' => $storeId,
        ])->each(function ($user) use ($storeId) {
            $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')
                ->where('store_id', $storeId)
                ->first();
            if ($staffRole) {
                setPermissionsTeamId($storeId);
                $user->assignRole($staffRole);
            }
            $this->assignUserToStore($user, $storeId, 'staff');
        });

        User::factory(2)->create([
            'store_id' => $storeId,
        ])->each(function ($user) use ($storeId) {
            $cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')
                ->where('store_id', $storeId)
                ->first();
            if ($cashierRole) {
                setPermissionsTeamId($storeId);
                $user->assignRole($cashierRole);
            }
            $this->assignUserToStore($user, $storeId, 'staff'); // Use staff for StoreUserAssignment
        });

        // Create Filament users (admin and owner)
        $this->call([
            FilamentUserSeeder::class,
        ]);
        
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            DiscountSeeder::class,
            OwnerDemoSeeder::class,
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
