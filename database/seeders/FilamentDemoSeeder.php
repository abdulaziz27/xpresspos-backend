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

        // Seed categories first
        $this->command->info('📂 Seeding Categories...');
        $this->call(CategorySeeder::class);

        // Seed basic products
        $this->command->info('📦 Seeding Basic Products...');
        $this->call(ProductSeeder::class);

        // Seed enhanced products
        $this->command->info('✨ Seeding Enhanced Products...');
        $this->call(EnhancedProductSeeder::class);

        // Seed product variants
        $this->command->info('🎛️ Seeding Product Variants...');
        $this->call(ProductVariantSeeder::class);

        // Seed tables (basic + enhanced)
        $this->command->info('🪑 Seeding Tables...');
        $this->call(TableSeeder::class);

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