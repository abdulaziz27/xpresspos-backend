<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FilamentDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Filament Demo Data Seeding...');
        $this->command->info('ℹ️  Using CoffeeShop seeders (replaced old seeders)...');

        // Use comprehensive CoffeeShop seeder which includes:
        // - Categories
        // - Products (with variants via modifier groups)
        // - Tables
        // - And more...
        $this->command->info('📦 Seeding Coffee Shop Data (Categories, Products, Tables, etc.)...');
        $this->call(\Database\Seeders\CoffeeShop\CoffeeShopSeeder::class);

        $this->command->info('✅ Filament Demo Data Seeding Completed!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('- Categories: Available for product organization');
        $this->command->info('- Products: Multiple products with variants available');
        $this->command->info('- Variants: Product options like size, milk type, etc.');
        $this->command->info('- Tables: Various table types and locations');
        $this->command->info('');
        $this->command->info('🎯 You can now access:');
        $this->command->info('- Products menu: /owner/products');
        $this->command->info('- Tables menu: /owner/tables');
        $this->command->info('- Store Settings: /owner/store-settings');
    }
}