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

        // Create a system admin user (skip role assignment for now)
        $systemAdmin = User::firstOrCreate(
            ['email' => 'admin@posxpress.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'store_id' => null, // System admin doesn't belong to any store
                'email_verified_at' => now(),
            ]
        );
        // Note: admin_sistem role will be assigned manually or through different mechanism

        // Create owner users for Filament login
        $ownerUser = User::firstOrCreate(
            ['email' => 'aziz@xpress.com'],
            [
                'name' => 'Abdul Aziz',
                'password' => Hash::make('password'),
                'store_id' => $storeId,
                'email_verified_at' => now(),
            ]
        );
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
        $testOwner = User::firstOrCreate(
            ['email' => 'owner@test.com'],
            [
                'name' => 'Test Owner',
                'password' => Hash::make('password'),
                'store_id' => $storeId,
                'email_verified_at' => now(),
            ]
        );
        if ($ownerRole) {
            setPermissionsTeamId($storeId);
            $testOwner->assignRole($ownerRole);
        }
        $this->assignUserToStore($testOwner, $storeId, 'owner', isPrimary: false);

        // Create test users with different roles and easy credentials
        
        // Create manager user for testing
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@test.com'],
            [
                'name' => 'Test Manager',
                'password' => Hash::make('password'),
                'store_id' => $storeId,
                'email_verified_at' => now(),
            ]
        );
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')
            ->where('store_id', $storeId)
            ->first();
        if ($managerRole) {
            setPermissionsTeamId($storeId);
            $managerUser->assignRole($managerRole);
        }
        $this->assignUserToStore($managerUser, $storeId, 'manager');

        // Create staff user for testing
        $staffUser = User::firstOrCreate(
            ['email' => 'staff@test.com'],
            [
                'name' => 'Test Staff',
                'password' => Hash::make('password'),
                'store_id' => $storeId,
                'email_verified_at' => now(),
            ]
        );
        $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')
            ->where('store_id', $storeId)
            ->first();
        if ($staffRole) {
            setPermissionsTeamId($storeId);
            $staffUser->assignRole($staffRole);
        }
        $this->assignUserToStore($staffUser, $storeId, 'staff');

        // Create additional staff users for testing
        $additionalStaff = [
            ['name' => 'Staff User 1', 'email' => 'staff1@test.com'],
            ['name' => 'Staff User 2', 'email' => 'staff2@test.com'],
        ];

        foreach ($additionalStaff as $staffData) {
            $user = User::firstOrCreate(
                ['email' => $staffData['email']],
                [
                    'name' => $staffData['name'],
                    'password' => Hash::make('password'),
                    'store_id' => $storeId,
                    'email_verified_at' => now(),
                ]
            );

            $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')
                ->where('store_id', $storeId)
                ->first();
            if ($staffRole) {
                setPermissionsTeamId($storeId);
                $user->assignRole($staffRole);
            }
            $this->assignUserToStore($user, $storeId, 'staff');
        }

        // Create additional cashier users for testing
        $additionalCashiers = [
            ['name' => 'Cashier User 1', 'email' => 'cashier1@test.com'],
            ['name' => 'Cashier User 2', 'email' => 'cashier2@test.com'],
        ];

        foreach ($additionalCashiers as $cashierData) {
            $user = User::firstOrCreate(
                ['email' => $cashierData['email']],
                [
                    'name' => $cashierData['name'],
                    'password' => Hash::make('password'),
                    'store_id' => $storeId,
                    'email_verified_at' => now(),
                ]
            );

            $cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')
                ->where('store_id', $storeId)
                ->first();
            if ($cashierRole) {
                setPermissionsTeamId($storeId);
                $user->assignRole($cashierRole);
            }
            $this->assignUserToStore($user, $storeId, 'staff'); // Use staff for StoreUserAssignment
        }

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
