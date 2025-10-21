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
        // First seed roles and permissions
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Create plans first
        $this->call([
            PlanSeeder::class,
        ]);

        // Create stores and subscriptions
        $this->call([
            StoreSeeder::class,
        ]);

        $storeId = config('demo.store_id');

        // Create a system admin user
        $systemAdmin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@posxpress.com',
            'password' => Hash::make('password'),
            'store_id' => null, // System admin doesn't belong to any store
        ]);
        $systemAdmin->assignRole('admin_sistem');

        // Create existing admin user with proper role
        $ownerUser = User::factory()->create([
            'name' => 'Abdul Aziz',
            'email' => 'aziz@xpress.com',
            'password' => Hash::make('password'),
            'store_id' => $storeId,
        ]);
        $ownerUser->assignRole('owner');
        $this->assignUserToStore($ownerUser, $storeId, 'owner', isPrimary: true);

        // Create test users with different roles
        User::factory(5)->create([
            'store_id' => $storeId,
        ])->each(function ($user) {
            $user->assignRole('cashier');
            $this->assignUserToStore($user, config('demo.store_id'), 'cashier');
        });

        User::factory(2)->create([
            'store_id' => $storeId,
        ])->each(function ($user) {
            $user->assignRole('manager');
            $this->assignUserToStore($user, config('demo.store_id'), 'manager');
        });

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
