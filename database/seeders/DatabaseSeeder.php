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

        // NOTE: Manager dan Staff tidak dibuat untuk demo
        // Demo hanya menggunakan Owner dan Cashier saja

        // Seed UOMs first (required for inventory items, recipes, etc.)
        $this->call([
            UomSeeder::class,
        ]);

        // Seed UOM conversions (for future use, currently not used in runtime)
        $this->call([
            UomConversionSeeder::class,
        ]);

        // Create Filament users (admin and owner)
        $this->call([
            FilamentUserSeeder::class,
        ]);
        
        // Seed Member Tiers (required before members)
        $this->call([
            MemberTierSeeder::class,
        ]);
        
        // Seed comprehensive Coffee Shop data
        $this->call([
            \Database\Seeders\CoffeeShop\CoffeeShopSeeder::class,
        ]);
        
        // Seed additional data (discounts, etc.)
        // Note: Promotions and vouchers are already seeded in CoffeeShopSeeder
        $this->call([
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
