<?php

namespace App\Console\Commands;

use App\Models\StockLevel;
use App\Models\InventoryMovement;
use Illuminate\Console\Command;

class RecalculateStockLevelCosts extends Command
{
    protected $signature = 'inventory:recalculate-costs 
                            {--dry-run : Show what would be updated without actually updating}';

    protected $description = 'Recalculate average_cost and total_value for all StockLevels based on inventory movements';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }

        $this->info('🔄 Recalculating average_cost and total_value for StockLevels...');
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        StockLevel::with('inventoryItem')->chunk(100, function ($stockLevels) use (&$updated, &$skipped, &$errors, $dryRun) {
            foreach ($stockLevels as $stockLevel) {
                try {
                    // Get all stock-in movements with unit_cost
                    $movements = InventoryMovement::where('inventory_item_id', $stockLevel->inventory_item_id)
                        ->where('store_id', $stockLevel->store_id)
                        ->whereIn('type', [
                            InventoryMovement::TYPE_PURCHASE,
                            InventoryMovement::TYPE_ADJUSTMENT_IN,
                            InventoryMovement::TYPE_TRANSFER_IN,
                            InventoryMovement::TYPE_RETURN
                        ])
                        ->whereNotNull('unit_cost')
                        ->where('unit_cost', '>', 0)
                        ->orderBy('created_at')
                        ->get();

                    $oldAverageCost = $stockLevel->average_cost;
                    $oldTotalValue = $stockLevel->total_value;

                    if ($movements->isEmpty()) {
                        // No movements with cost, try to use InventoryItem.default_cost
                        $inventoryItem = $stockLevel->inventoryItem;
                        
                        if ($inventoryItem && $inventoryItem->default_cost > 0) {
                            $newAverageCost = $inventoryItem->default_cost;
                            $newTotalValue = $stockLevel->current_stock * $newAverageCost;
                            
                            if (!$dryRun) {
                                $stockLevel->average_cost = $newAverageCost;
                                $stockLevel->total_value = $newTotalValue;
                                $stockLevel->save();
                            }
                            
                            $itemName = $inventoryItem->name;
                            $storeName = $stockLevel->store ? $stockLevel->store->name : 'N/A';
                            $this->line("  ✓ {$itemName} (Store: {$storeName})");
                            $this->line("    Using default_cost: {$newAverageCost} → total_value: {$newTotalValue}");
                            $updated++;
                        } else {
                            $itemName = $inventoryItem ? $inventoryItem->name : 'Unknown';
                            $storeName = $stockLevel->store ? $stockLevel->store->name : 'N/A';
                            $this->warn("  ⚠ {$itemName} (Store: {$storeName})");
                            $this->warn("    No movements with cost and no default_cost. Skipping.");
                            $skipped++;
                        }
                        continue;
                    }

                    // Recalculate weighted average
                    $totalValue = 0;
                    $totalQuantity = 0;

                    foreach ($movements as $movement) {
                        $totalValue += $movement->quantity * $movement->unit_cost;
                        $totalQuantity += $movement->quantity;
                    }

                    if ($totalQuantity > 0) {
                        $newAverageCost = $totalValue / $totalQuantity;
                        $newTotalValue = $stockLevel->current_stock * $newAverageCost;

                        if (!$dryRun) {
                            $stockLevel->average_cost = $newAverageCost;
                            $stockLevel->total_value = $newTotalValue;
                            $stockLevel->save();
                        }

                        $itemName = $stockLevel->inventoryItem?->name ?? 'Unknown';
                        $storeName = $stockLevel->store->name ?? 'N/A';
                        
                        $this->line("  ✓ {$itemName} (Store: {$storeName})");
                        $this->line("    average_cost: {$oldAverageCost} → {$newAverageCost}");
                        $this->line("    total_value: {$oldTotalValue} → {$newTotalValue}");
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Error processing StockLevel {$stockLevel->id}: {$e->getMessage()}");
                    $errors++;
                }
            }
        });

        $this->newLine();
        $this->info("✅ Done!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Errors', $errors],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('💡 This was a dry run. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }
}

