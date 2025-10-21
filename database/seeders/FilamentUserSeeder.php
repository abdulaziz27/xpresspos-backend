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

        if (!$admin->hasRole('admin_sistem')) {
            $admin->assignRole('admin_sistem');
        }

        // Create Store Owner user
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.com'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        if (!$owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }

        // Ensure owner user has a store context for API access
        if (!$owner->store_id) {
            if ($primaryStoreId = Store::value('id')) {
                $owner->forceFill(['store_id' => $primaryStoreId])->save();
                $this->assignUserToStore($owner, $primaryStoreId, 'owner');
            }
        } else {
            $this->assignUserToStore($owner, $owner->store_id, 'owner');
        }

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
