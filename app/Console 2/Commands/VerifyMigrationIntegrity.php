<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyMigrationIntegrity extends Command
{
    protected $signature = 'migrate:verify-integrity';
    protected $description = 'Verify migration integrity: FK constraints, orphaned records, and data consistency';
    
    private $passed = 0;
    private $failed = 0;
    private $warnings = 0;
    
    public function handle()
    {
        $this->info('🔍 Verifying Migration Integrity...');
        $this->newLine();
        
        // Check FK constraints
        $this->checkForeignKeyConstraints();
        
        // Check for orphaned records
        $this->checkOrphanedRecords();
        
        // Verify tenant_id population
        $this->verifyTenantIdPopulation();
        
        // Verify inventory schema changes
        $this->verifyInventorySchemaChanges();
        
        // Verify recipe schema changes
        $this->verifyRecipeSchemaChanges();
        
        // Display summary
        $this->displaySummary();
        
        return $this->failed === 0 ? 0 : 1;
    }
    
    private function checkForeignKeyConstraints()
    {
        $this->info('📋 Checking Foreign Key Constraints...');
        
        $fkChecks = [
            ['table' => 'user_tenant_access', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id'],
            ['table' => 'user_tenant_access', 'column' => 'tenant_id', 'ref_table' => 'tenants', 'ref_column' => 'id'],
            ['table' => 'stores', 'column' => 'tenant_id', 'ref_table' => 'tenants', 'ref_column' => 'id'],
            ['table' => 'subscriptions', 'column' => 'tenant_id', 'ref_table' => 'tenants', 'ref_column' => 'id'],
            ['table' => 'inventory_items', 'column' => 'store_id', 'ref_table' => 'stores', 'ref_column' => 'id'],
            ['table' => 'inventory_items', 'column' => 'uom_id', 'ref_table' => 'uoms', 'ref_column' => 'id'],
            ['table' => 'inventory_stock_levels', 'column' => 'inventory_item_id', 'ref_table' => 'inventory_items', 'ref_column' => 'id'],
            ['table' => 'inventory_movements', 'column' => 'inventory_item_id', 'ref_table' => 'inventory_items', 'ref_column' => 'id'],
            ['table' => 'inventory_movements', 'column' => 'lot_id', 'ref_table' => 'inventory_lots', 'ref_column' => 'id'],
            ['table' => 'recipe_items', 'column' => 'inventory_item_id', 'ref_table' => 'inventory_items', 'ref_column' => 'id'],
            ['table' => 'recipe_items', 'column' => 'uom_id', 'ref_table' => 'uoms', 'ref_column' => 'id'],
            ['table' => 'suppliers', 'column' => 'tenant_id', 'ref_table' => 'tenants', 'ref_column' => 'id'],
            ['table' => 'purchase_orders', 'column' => 'supplier_id', 'ref_table' => 'suppliers', 'ref_column' => 'id'],
            ['table' => 'promotions', 'column' => 'tenant_id', 'ref_table' => 'tenants', 'ref_column' => 'id'],
            ['table' => 'vouchers', 'column' => 'tenant_id', 'ref_table' => 'tenants', 'ref_column' => 'id'],
        ];
        
        foreach ($fkChecks as $check) {
            if (!Schema::hasTable($check['table'])) {
                $this->warn("  ⚠️  Table '{$check['table']}' does not exist");
                $this->warnings++;
                continue;
            }
            
            $fkExists = $this->foreignKeyExists(
                $check['table'],
                $check['column'],
                $check['ref_table'],
                $check['ref_column']
            );
            
            if ($fkExists) {
                $this->line("  ✅ FK: {$check['table']}.{$check['column']} -> {$check['ref_table']}.{$check['ref_column']}");
                $this->passed++;
            } else {
                $this->error("  ❌ FK Missing: {$check['table']}.{$check['column']} -> {$check['ref_table']}.{$check['ref_column']}");
                $this->failed++;
            }
        }
        
        $this->newLine();
    }
    
    private function checkOrphanedRecords()
    {
        $this->info('🔍 Checking for Orphaned Records...');
        
        // Check subscriptions without tenant_id
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'tenant_id')) {
            $orphaned = DB::table('subscriptions')->whereNull('tenant_id')->count();
            if ($orphaned > 0) {
                $this->error("  ❌ Found {$orphaned} subscriptions without tenant_id");
                $this->failed++;
            } else {
                $this->line("  ✅ No orphaned subscriptions");
                $this->passed++;
            }
        }
        
        // Check stores without tenant_id
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'tenant_id')) {
            $orphaned = DB::table('stores')->whereNull('tenant_id')->count();
            if ($orphaned > 0) {
                $this->error("  ❌ Found {$orphaned} stores without tenant_id");
                $this->failed++;
            } else {
                $this->line("  ✅ No orphaned stores");
                $this->passed++;
            }
        }
        
        // Check members without tenant_id
        if (Schema::hasTable('members') && Schema::hasColumn('members', 'tenant_id')) {
            $orphaned = DB::table('members')->whereNull('tenant_id')->count();
            if ($orphaned > 0) {
                $this->error("  ❌ Found {$orphaned} members without tenant_id");
                $this->failed++;
            } else {
                $this->line("  ✅ No orphaned members");
                $this->passed++;
            }
        }
        
        // Check member_tiers without tenant_id
        if (Schema::hasTable('member_tiers') && Schema::hasColumn('member_tiers', 'tenant_id')) {
            $orphaned = DB::table('member_tiers')->whereNull('tenant_id')->count();
            if ($orphaned > 0) {
                $this->error("  ❌ Found {$orphaned} member_tiers without tenant_id");
                $this->failed++;
            } else {
                $this->line("  ✅ No orphaned member_tiers");
                $this->passed++;
            }
        }
        
        // Check loyalty_point_transactions without tenant_id
        if (Schema::hasTable('loyalty_point_transactions') && Schema::hasColumn('loyalty_point_transactions', 'tenant_id')) {
            $orphaned = DB::table('loyalty_point_transactions')->whereNull('tenant_id')->count();
            if ($orphaned > 0) {
                $this->error("  ❌ Found {$orphaned} loyalty_point_transactions without tenant_id");
                $this->failed++;
            } else {
                $this->line("  ✅ No orphaned loyalty_point_transactions");
                $this->passed++;
            }
        }
        
        $this->newLine();
    }
    
    private function verifyTenantIdPopulation()
    {
        $this->info('🏢 Verifying tenant_id Population...');
        
        $tables = ['subscriptions', 'stores', 'members', 'member_tiers', 'loyalty_point_transactions', 'activity_logs', 'permission_audit_logs'];
        
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("  ⚠️  Table '{$table}' does not exist");
                $this->warnings++;
                continue;
            }
            
            if (!Schema::hasColumn($table, 'tenant_id')) {
                $this->error("  ❌ Column 'tenant_id' missing in '{$table}'");
                $this->failed++;
                continue;
            }
            
            $total = DB::table($table)->count();
            $withTenantId = DB::table($table)->whereNotNull('tenant_id')->count();
            
            if ($total === 0) {
                $this->line("  ✅ {$table}: No records (OK)");
                $this->passed++;
            } elseif ($total === $withTenantId) {
                $this->line("  ✅ {$table}: All {$total} records have tenant_id");
                $this->passed++;
            } else {
                $missing = $total - $withTenantId;
                $this->error("  ❌ {$table}: {$missing}/{$total} records missing tenant_id");
                $this->failed++;
            }
        }
        
        $this->newLine();
    }
    
    private function verifyInventorySchemaChanges()
    {
        $this->info('📦 Verifying Inventory Schema Changes...');
        
        // Check inventory_stock_levels has inventory_item_id (not product_id)
        if (Schema::hasTable('inventory_stock_levels')) {
            if (Schema::hasColumn('inventory_stock_levels', 'inventory_item_id')) {
                $this->line("  ✅ inventory_stock_levels has 'inventory_item_id' column");
                $this->passed++;
            } else {
                $this->error("  ❌ inventory_stock_levels missing 'inventory_item_id' column");
                $this->failed++;
            }
            
            if (Schema::hasColumn('inventory_stock_levels', 'product_id')) {
                $this->error("  ❌ inventory_stock_levels still has old 'product_id' column");
                $this->failed++;
            } else {
                $this->line("  ✅ inventory_stock_levels 'product_id' column removed");
                $this->passed++;
            }
        }
        
        // Check inventory_movements has inventory_item_id
        if (Schema::hasTable('inventory_movements')) {
            if (Schema::hasColumn('inventory_movements', 'inventory_item_id')) {
                $this->line("  ✅ inventory_movements has 'inventory_item_id' column");
                $this->passed++;
            } else {
                $this->error("  ❌ inventory_movements missing 'inventory_item_id' column");
                $this->failed++;
            }
        }
        
        // Check UOM tables exist
        if (Schema::hasTable('uoms')) {
            $this->line("  ✅ 'uoms' table exists");
            $this->passed++;
        } else {
            $this->error("  ❌ 'uoms' table missing");
            $this->failed++;
        }
        
        if (Schema::hasTable('uom_conversions')) {
            $this->line("  ✅ 'uom_conversions' table exists");
            $this->passed++;
        } else {
            $this->error("  ❌ 'uom_conversions' table missing");
            $this->failed++;
        }
        
        $this->newLine();
    }
    
    private function verifyRecipeSchemaChanges()
    {
        $this->info('🍳 Verifying Recipe Schema Changes...');
        
        if (!Schema::hasTable('recipe_items')) {
            $this->error("  ❌ 'recipe_items' table does not exist");
            $this->failed++;
            return;
        }
        
        // Check recipe_items has inventory_item_id (not ingredient_product_id)
        if (Schema::hasColumn('recipe_items', 'inventory_item_id')) {
            $this->line("  ✅ recipe_items has 'inventory_item_id' column");
            $this->passed++;
        } else {
            $this->error("  ❌ recipe_items missing 'inventory_item_id' column");
            $this->failed++;
        }
        
        if (Schema::hasColumn('recipe_items', 'ingredient_product_id')) {
            $this->error("  ❌ recipe_items still has old 'ingredient_product_id' column");
            $this->failed++;
        } else {
            $this->line("  ✅ recipe_items 'ingredient_product_id' column removed");
            $this->passed++;
        }
        
        // Check recipe_items has uom_id
        if (Schema::hasColumn('recipe_items', 'uom_id')) {
            $this->line("  ✅ recipe_items has 'uom_id' column");
            $this->passed++;
        } else {
            $this->error("  ❌ recipe_items missing 'uom_id' column");
            $this->failed++;
        }
        
        $this->newLine();
    }
    
    private function foreignKeyExists(string $table, string $column, string $refTable, string $refColumn): bool
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME = ?
            AND REFERENCED_COLUMN_NAME = ?
        ", [$table, $column, $refTable, $refColumn]);
        
        return !empty($foreignKeys);
    }
    
    private function displaySummary()
    {
        $this->info('📊 Verification Summary:');
        $this->newLine();
        
        $this->line("  ✅ Passed: {$this->passed}");
        
        if ($this->warnings > 0) {
            $this->warn("  ⚠️  Warnings: {$this->warnings}");
        }
        
        if ($this->failed > 0) {
            $this->error("  ❌ Failed: {$this->failed}");
        } else {
            $this->info('  🎉 All checks passed!');
        }
        
        $this->newLine();
    }
}
