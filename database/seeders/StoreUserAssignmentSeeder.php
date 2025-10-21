<?php

namespace Database\Seeders;

use App\Enums\AssignmentRoleEnum;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class StoreUserAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users and stores
        $users = User::all();
        $stores = Store::all();

        if ($users->isEmpty() || $stores->isEmpty()) {
            $this->command->info('No users or stores found. Skipping store user assignments seeding.');
            return;
        }

        // Create assignments for existing users with store_id
        $usersWithStores = $users->whereNotNull('store_id');
        
        foreach ($usersWithStores as $user) {
            $store = $stores->firstWhere('id', $user->store_id);
            
            if (!$store) {
                continue;
            }

            // Check if assignment already exists
            $existingAssignment = StoreUserAssignment::where('user_id', $user->id)
                ->where('store_id', $store->id)
                ->first();

            if ($existingAssignment) {
                continue;
            }

            // Determine role based on user's existing roles
            $role = AssignmentRoleEnum::STAFF; // default
            
            if ($user->hasRole('owner')) {
                $role = AssignmentRoleEnum::OWNER;
            } elseif ($user->hasRole('admin')) {
                $role = AssignmentRoleEnum::ADMIN;
            } elseif ($user->hasRole('manager')) {
                $role = AssignmentRoleEnum::MANAGER;
            }

            StoreUserAssignment::create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'assignment_role' => $role,
                'is_primary' => true, // First assignment is primary
            ]);

            $this->command->info("Created assignment for user {$user->name} to store {$store->name} as {$role->value}");
        }

        // Create some additional sample assignments for demo purposes
        if ($users->count() >= 2 && $stores->count() >= 2) {
            $firstUser = $users->first();
            $secondStore = $stores->skip(1)->first();

            // Create secondary assignment if it doesn't exist
            $existingSecondary = StoreUserAssignment::where('user_id', $firstUser->id)
                ->where('store_id', $secondStore->id)
                ->first();

            if (!$existingSecondary) {
                StoreUserAssignment::create([
                    'user_id' => $firstUser->id,
                    'store_id' => $secondStore->id,
                    'assignment_role' => AssignmentRoleEnum::MANAGER,
                    'is_primary' => false,
                ]);

                $this->command->info("Created secondary assignment for user {$firstUser->name} to store {$secondStore->name} as manager");
            }
        }

        $this->command->info('Store user assignments seeding completed.');
    }
}