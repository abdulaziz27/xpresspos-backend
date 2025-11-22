<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\User;
use App\Models\Store;

class CoffeeShopCashSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic cash sessions for the last 30 days.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        $stores = Store::where('tenant_id', $tenantId)->get();

        if ($stores->isEmpty()) {
            $this->command->error('No stores found. Make sure StoreSeeder runs first.');
            return;
        }

        // Get users per store - cashier/owner yang assigned ke store tersebut
        $storeUsers = [];
        foreach ($stores as $store) {
            $storeUsers[$store->id] = User::whereHas('storeAssignments', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })->get();
            
            // Fallback to owner if no users assigned
            if ($storeUsers[$store->id]->isEmpty()) {
                $owner = User::where('email', 'owner@xpresspos.id')->first();
                if ($owner) {
                    $storeUsers[$store->id] = collect([$owner]);
                }
            }
        }

        // Create cash sessions for the last 30 days for each store
        $sessionCount = 0;
        $daysBack = 30;

        foreach ($stores as $store) {
            for ($day = 0; $day <= $daysBack; $day++) {
                $date = Carbon::now()->subDays($day);
                
                // Usually 1-2 sessions per day (morning and evening shift)
                $sessionsPerDay = rand(1, 2);

                for ($sessionNum = 0; $sessionNum < $sessionsPerDay; $sessionNum++) {
                    // Use cashier/owner yang assigned ke store ini
                    $user = $storeUsers[$store->id]->random();
                    
                    // Determine shift time
                    if ($sessionNum === 0) {
                        // Morning shift: 7 AM - 2 PM
                        $openedAt = $date->copy()->setTime(7, 0)->addMinutes(rand(0, 30));
                        $closedAt = $date->copy()->setTime(14, 0)->addMinutes(rand(0, 60));
                    } else {
                        // Evening shift: 2 PM - 9 PM
                        $openedAt = $date->copy()->setTime(14, 0)->addMinutes(rand(0, 30));
                        $closedAt = $date->copy()->setTime(21, 0)->addMinutes(rand(0, 60));
                    }

                    // Get cash payments for this time period
                    $cashPayments = Payment::query()->withoutGlobalScopes()
                        ->where('store_id', $store->id)
                        ->where('payment_method', 'cash')
                        ->where('status', 'completed')
                        ->whereBetween('processed_at', [$openedAt, $closedAt])
                        ->sum('amount');

                    // Opening balance (usually small amount for change)
                    $openingBalance = rand(500000, 1000000); // 500k - 1M

                    // Cash sales
                    $cashSales = $cashPayments;

                    // Cash expenses (random small expenses)
                    $cashExpenses = rand(0, 200000); // Max 200k per shift

                    // Expected balance
                    $expectedBalance = $openingBalance + $cashSales - $cashExpenses;

                    // Closing balance (with small variance)
                    $variance = rand(-50000, 50000); // ±50k variance
                    $closingBalance = $expectedBalance + $variance;

                    // Status (closed for past sessions, open for today)
                    $status = $closedAt->isPast() ? 'closed' : 'open';

                    // Create cash session
                    CashSession::query()->withoutGlobalScopes()->create([
                        'store_id' => $store->id,
                        'user_id' => $user->id,
                        'opening_balance' => $openingBalance,
                        'closing_balance' => $status === 'closed' ? $closingBalance : null,
                        'expected_balance' => $expectedBalance,
                        'cash_sales' => $cashSales,
                        'cash_expenses' => $cashExpenses,
                        'variance' => $status === 'closed' ? $variance : 0,
                        'status' => $status,
                        'opened_at' => $openedAt,
                        'closed_at' => $status === 'closed' ? $closedAt : null,
                        'notes' => $status === 'closed' && abs($variance) > 30000 ? 'Variance noted' : null,
                        'created_at' => $openedAt,
                        'updated_at' => $status === 'closed' ? $closedAt : $openedAt,
                    ]);

                    $sessionCount++;
                }
            }
        }

        $this->command->info("✅ Created {$sessionCount} cash sessions successfully!");
    }
}

