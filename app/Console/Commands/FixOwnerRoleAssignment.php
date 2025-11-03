<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class FixOwnerRoleAssignment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fix-owner-role 
                            {email=owner@xpresspos.com : Email of the owner user to fix}
                            {--store-id= : Specific store ID to assign (optional)}
                            {--force : Force reassignment even if role already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix owner role assignment for a user with proper team context';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $storeIdOption = $this->option('store-id');
        $force = $this->option('force');

        $this->info("🔧 Fixing owner role assignment for: {$email}");

        // Find user
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("❌ User with email '{$email}' not found!");
            return self::FAILURE;
        }

        $this->info("✅ Found user: {$user->name} (ID: {$user->id})");

        // Determine store_id
        $storeId = $storeIdOption ?: $user->store_id;
        if (!$storeId) {
            // Try to get from primary store assignment
            $primaryStore = $user->primaryStore();
            if ($primaryStore) {
                $storeId = $primaryStore->id;
                $this->info("📍 Using primary store assignment: {$storeId}");
            } else {
                // Use first available store
                $store = Store::first();
                if (!$store) {
                    $this->error("❌ No store found in database!");
                    return self::FAILURE;
                }
                $storeId = $store->id;
                $this->warn("⚠️  User has no store_id, using first store: {$storeId}");
            }
        }

        // Verify store exists
        $store = Store::find($storeId);
        if (!$store) {
            $this->error("❌ Store with ID '{$storeId}' not found!");
            return self::FAILURE;
        }

        $this->info("🏪 Store: {$store->name} (ID: {$storeId})");

        // Update user's store_id if needed
        if ($user->store_id !== $storeId) {
            $user->store_id = $storeId;
            $user->save();
            $this->info("✅ Updated user's store_id to: {$storeId}");
        }

        // Set team context
        setPermissionsTeamId($storeId);
        $this->info("✅ Set team context to: {$storeId}");

        // Find or create owner role for this store
        $ownerRole = Role::where('name', 'owner')
            ->where('store_id', $storeId)
            ->first();

        if (!$ownerRole) {
            $this->warn("⚠️  Owner role not found for store {$storeId}, creating it...");
            
            // Check if there's a role seeder we should run first
            $this->call('db:seed', ['--class' => 'PermissionsAndRolesSeeder']);
            
            // Try again
            $ownerRole = Role::where('name', 'owner')
                ->where('store_id', $storeId)
                ->first();
            
            if (!$ownerRole) {
                $this->error("❌ Failed to create owner role. Please run PermissionsAndRolesSeeder first.");
                return self::FAILURE;
            }
        }

        $this->info("✅ Found owner role: {$ownerRole->name} (ID: {$ownerRole->id}, Store: {$ownerRole->store_id})");

        // Check current role assignment
        $currentRoles = $user->roles()->where('store_id', $storeId)->get();
        $hasOwnerRole = $currentRoles->contains('id', $ownerRole->id);

        if ($hasOwnerRole && !$force) {
            $this->info("✅ User already has owner role assigned!");
        } else {
            // Remove existing role if force
            if ($hasOwnerRole && $force) {
                $user->removeRole($ownerRole);
                $this->info("🔄 Removed existing owner role (force mode)");
            }

            // Assign role with team context
            setPermissionsTeamId($storeId);
            $user->assignRole($ownerRole);
            $this->info("✅ Assigned owner role to user!");
        }

        // Ensure store assignment exists
        $assignment = StoreUserAssignment::updateOrCreate(
            [
                'store_id' => $storeId,
                'user_id' => $user->id,
            ],
            [
                'assignment_role' => 'owner',
                'is_primary' => true,
            ]
        );
        $this->info("✅ Store assignment created/updated!");

        // Clear permission cache
        $this->call('permission:cache-reset');

        // Verify assignment
        setPermissionsTeamId($storeId);
        $hasRole = $user->hasRole('owner');
        $roleNames = $user->getRoleNames();

        // Check database directly
        $dbCheck = \DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', $user->id)
            ->where('role_id', $ownerRole->id)
            ->where('store_id', $storeId)
            ->exists();

        $this->newLine();
        $this->info("📊 Verification:");
        $this->line("   User ID: {$user->id}");
        $this->line("   Email: {$user->email}");
        $this->line("   Store ID: {$user->store_id}");
        $this->line("   Role ID: {$ownerRole->id}");
        $this->line("   DB Record Exists: " . ($dbCheck ? "✅ YES" : "❌ NO"));
        $this->line("   Has Owner Role (hasRole): " . ($hasRole ? "✅ YES" : "❌ NO"));
        $this->line("   Role Names: " . ($roleNames->isNotEmpty() ? $roleNames->implode(', ') : 'None'));
        $this->line("   Store Assignment: ✅ EXISTS");

        if ($hasRole) {
            $this->newLine();
            $this->info("🎉 Success! User role assignment is now correct.");
            return self::SUCCESS;
        } else {
            $this->newLine();
            $this->warn("⚠️  Warning: hasRole('owner') returns false. Please check:");
            $this->line("   1. Run: php artisan permission:cache-reset");
            $this->line("   2. Verify model_has_roles table has entry with correct team_id");
            return self::FAILURE;
        }
    }
}

