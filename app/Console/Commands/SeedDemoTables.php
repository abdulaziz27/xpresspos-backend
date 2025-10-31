<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\Table;
use Illuminate\Console\Command;

class SeedDemoTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:seed-tables {--store=} {--count=3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create 2-3 dummy tables (meja) for an owner dashboard preview';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeId = $this->option('store');
        $count = (int) $this->option('count');
        if ($count < 1) {
            $count = 3;
        }

        $store = null;
        if ($storeId) {
            $store = Store::find($storeId);
            if (!$store) {
                $this->error("Store with ID {$storeId} not found.");
                return self::FAILURE;
            }
        } else {
            $store = Store::first();
            if (!$store) {
                $store = Store::create([
                    'name' => 'Demo Store',
                    'email' => 'demo@example.com',
                    'phone' => '0000000000',
                    'address' => 'Demo Address',
                    'status' => 'active',
                ]);
                $this->info('No store found. Created a Demo Store.');
            }
        }

        $this->info('Seeding demo tables for store: ' . $store->name . ' (' . $store->id . ')');

        // Create simple numbered tables if they don\'t already exist
        $created = 0;
        for ($i = 1; $i <= $count; $i++) {
            $tableNumber = $i;
            $name = 'Meja ' . $i;

            $existing = Table::withoutGlobalScopes()->where('store_id', $store->id)
                ->where(function ($q) use ($tableNumber, $name) {
                    $q->where('table_number', $tableNumber)->orWhere('name', $name);
                })
                ->first();

            if ($existing) {
                $this->line("- Skip: {$name} already exists");
                continue;
            }

            Table::withoutGlobalScopes()->create([
                'store_id' => $store->id,
                'table_number' => $tableNumber,
                'name' => $name,
                'capacity' => 4,
                'status' => 'available',
                'location' => 'indoor',
                'is_active' => true,
                'notes' => 'Dummy data for preview',
            ]);
            $this->line("- Created: {$name}");
            $created++;
        }

        $this->info("Done. Created {$created} table(s).");
        return self::SUCCESS;
    }
}


