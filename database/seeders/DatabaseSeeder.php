<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        \App\Models\User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@posxpress.com',
            'password' => Hash::make('password'),
            'store_id' => null, // System admin doesn't belong to any store
        ])->assignRole('admin_sistem');

        // Create existing admin user with proper role
        \App\Models\User::factory()->create([
            'name' => 'Abdul Aziz',
            'email' => 'aziz@xpress.com',
            'password' => Hash::make('password'),
            'store_id' => $storeId,
        ])->assignRole('owner');

        // Create test users with different roles
        \App\Models\User::factory(5)->create([
            'store_id' => $storeId,
        ])->each(function ($user) {
            $user->assignRole('cashier');
        });

        \App\Models\User::factory(2)->create([
            'store_id' => $storeId,
        ])->each(function ($user) {
            $user->assignRole('manager');
        });

        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            DiscountSeeder::class,
        ]);
    }
}
