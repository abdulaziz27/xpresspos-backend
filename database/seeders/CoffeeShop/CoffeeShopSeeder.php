<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;

class CoffeeShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Main seeder that orchestrates all coffee shop seeders in the correct order.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting Coffee Shop seeding process...');

        // Step 1: Categories
        $this->command->info('Step 1: Creating categories...');
        $this->call(CoffeeShopCategorySeeder::class);

        // Step 2: Inventory Items
        $this->command->info('Step 2: Creating inventory items...');
        $this->call(CoffeeShopInventoryItemSeeder::class);

        // Step 3: Suppliers
        $this->command->info('Step 3: Creating suppliers...');
        $this->call(CoffeeShopSupplierSeeder::class);

        // Step 4: Modifier Groups
        $this->command->info('Step 4: Creating modifier groups...');
        $this->call(CoffeeShopModifierGroupSeeder::class);

        // Step 5: Products
        $this->command->info('Step 5: Creating products...');
        $this->call(CoffeeShopProductSeeder::class);

        // Step 6: Recipes
        $this->command->info('Step 6: Creating recipes...');
        $this->call(CoffeeShopRecipeSeeder::class);

        // Step 7: Stock Levels
        $this->command->info('Step 7: Creating stock levels...');
        $this->call(CoffeeShopStockLevelSeeder::class);

        // Step 8: Tables
        $this->command->info('Step 8: Creating tables...');
        $this->call(CoffeeShopTableSeeder::class);

        // Step 9: Promotions (must be after products and member tiers)
        $this->command->info('Step 9: Creating promotions...');
        $this->call(CoffeeShopPromotionSeeder::class);

        // Step 10: Vouchers (must be after promotions)
        $this->command->info('Step 10: Creating vouchers...');
        $this->call(CoffeeShopVoucherSeeder::class);

        // Step 11: Members
        $this->command->info('Step 11: Creating members...');
        $this->call(CoffeeShopMemberSeeder::class);

        // Step 12: Orders
        $this->command->info('Step 12: Creating orders...');
        $this->call(CoffeeShopOrderSeeder::class);

        // Step 13: Payments
        $this->command->info('Step 13: Creating payments...');
        $this->call(CoffeeShopPaymentSeeder::class);

        // Step 14: Cash Sessions
        $this->command->info('Step 14: Creating cash sessions...');
        $this->call(CoffeeShopCashSessionSeeder::class);

        // Step 15: Expenses
        $this->command->info('Step 15: Creating expenses...');
        $this->call(CoffeeShopExpenseSeeder::class);

        // Step 16: Purchase Orders
        $this->command->info('Step 16: Creating purchase orders...');
        $this->call(CoffeeShopPurchaseOrderSeeder::class);

        // Step 17: Inventory Adjustments
        $this->command->info('Step 17: Creating inventory adjustments...');
        $this->call(CoffeeShopInventoryAdjustmentSeeder::class);

        // Step 18: Inventory Transfers
        $this->command->info('Step 18: Creating inventory transfers...');
        $this->call(CoffeeShopInventoryTransferSeeder::class);

        // Step 19: Product Price Histories
        $this->command->info('Step 19: Creating product price histories...');
        $this->call(CoffeeShopProductPriceHistorySeeder::class);

        // Step 20: Subscription Usage
        $this->command->info('Step 20: Creating subscription usage...');
        $this->call(CoffeeShopSubscriptionUsageSeeder::class);

        // Step 21: Subscription Payments
        $this->command->info('Step 21: Creating subscription payments...');
        $this->call(CoffeeShopSubscriptionPaymentSeeder::class);

        // Step 22: Invoices
        $this->command->info('Step 22: Creating invoices...');
        $this->call(CoffeeShopInvoiceSeeder::class);

        $this->command->info('✅ Coffee Shop seeding completed successfully!');
    }
}

