<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FilamentUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create System Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@xpresspos.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign admin_sistem role (global role without store_id)
        $adminSistemRole = \Spatie\Permission\Models\Role::where('name', 'admin_sistem')
            ->whereNull('store_id')
            ->first();
        if ($adminSistemRole && !$admin->hasRole($adminSistemRole)) {
            $admin->assignRole($adminSistemRole);
        }

        // Get primary store ID
        $primaryStoreId = Store::value('id');
        if (!$primaryStoreId) {
            $this->command->error('No store found! Please run StoreSeeder first.');
            return;
        }

        // Create Store Owner user
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.com'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'store_id' => $primaryStoreId,
            ]
        );

        // Assign owner role for the specific store
        $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
            ->where('store_id', $primaryStoreId)
            ->first();
        
        if ($ownerRole && !$owner->hasRole($ownerRole)) {
            $owner->assignRole($ownerRole);
        }

        // Ensure owner user has store context
        $this->assignUserToStore($owner, $primaryStoreId, 'owner');

        $this->command->info('Filament users created successfully!');
        $this->command->info('Admin: admin@xpresspos.com / password123');
        $this->command->info('Owner: owner@xpresspos.com / password123');
    }

    private function assignUserToStore(User $user, string $storeId, string $role): void
    {
        StoreUserAssignment::updateOrCreate(
            [
                'store_id' => $storeId,
                'user_id' => $user->id,
            ],
            [
                'assignment_role' => $role,
                'is_primary' => true,
            ]
        );
    }
}
