<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\Expense;
use App\Models\User;
use App\Models\Store;

class CoffeeShopExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic expenses for coffee shop operations.
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

        $expenseCategories = [
            'Operational' => [
                'Listrik',
                'Air',
                'Internet',
                'Maintenance',
                'Cleaning Supplies',
            ],
            'Supplies' => [
                'Toilet Paper',
                'Hand Soap',
                'Cleaning Products',
                'Paper Towels',
            ],
            'Marketing' => [
                'Social Media Ads',
                'Flyers',
                'Event Sponsorship',
            ],
            'Other' => [
                'Parking Fee',
                'Permit Fee',
                'Miscellaneous',
            ],
        ];

        $expenseCount = 0;
        $daysBack = 30;

        // Create expenses for each store
        foreach ($stores as $store) {
            for ($day = 0; $day <= $daysBack; $day++) {
                $date = Carbon::now()->subDays($day);
                
                // Generate 0-3 expenses per day per store
                $expensesPerDay = rand(0, 3);

                for ($expNum = 0; $expNum < $expensesPerDay; $expNum++) {
                    // Use cashier/owner yang assigned ke store ini
                    $user = $storeUsers[$store->id]->random();
                    
                    // Select random category and subcategory
                    $category = array_rand($expenseCategories);
                    $subcategories = $expenseCategories[$category];
                    $subcategory = $subcategories[array_rand($subcategories)];

                    // Amount based on category
                    $amount = match ($category) {
                        'Operational' => rand(50000, 500000), // 50k - 500k
                        'Supplies' => rand(20000, 150000),    // 20k - 150k
                        'Marketing' => rand(100000, 1000000), // 100k - 1M
                        default => rand(10000, 100000),       // 10k - 100k
                    };

                    // Find cash session for this date if exists
                    $cashSession = \App\Models\CashSession::query()->withoutGlobalScopes()
                        ->where('store_id', $store->id)
                        ->whereDate('opened_at', $date->toDateString())
                        ->first();

                    // Random time during business hours
                    $expenseTime = $date->copy()->setTime(rand(8, 20), rand(0, 59));

                    // Generate vendor name
                    $vendors = [
                        'PT Toko Bangunan',
                        'Toko Sembako Sejahtera',
                        'Kantor Pajak',
                        'PLN',
                        'PDAM',
                        'Internet Provider',
                        'Supplier Bahan',
                        null, // Sometimes no vendor
                    ];

                    // Create expense
                    Expense::query()->withoutGlobalScopes()->create([
                        'store_id' => $store->id,
                        'cash_session_id' => $cashSession?->id,
                        'user_id' => $user->id,
                        'category' => $category,
                        'description' => $subcategory . ' - ' . $this->generateDescription($category, $subcategory),
                        'amount' => $amount,
                        'receipt_number' => rand(0, 100) < 30 ? 'REC-' . now()->format('Ymd') . '-' . rand(1000, 9999) : null, // 30% chance
                        'vendor' => $vendors[array_rand($vendors)],
                        'expense_date' => $date->toDateString(),
                        'notes' => rand(0, 100) < 20 ? 'Receipt attached' : null, // 20% chance
                        'created_at' => $expenseTime,
                        'updated_at' => $expenseTime,
                    ]);

                    $expenseCount++;
                }
            }
        }

        $this->command->info("✅ Created {$expenseCount} expenses successfully!");
    }

    /**
     * Generate description based on category and subcategory.
     */
    private function generateDescription(string $category, string $subcategory): string
    {
        $descriptions = [
            'Listrik' => 'Monthly electricity bill',
            'Air' => 'Water bill payment',
            'Internet' => 'Internet subscription',
            'Maintenance' => 'Equipment maintenance',
            'Cleaning Supplies' => 'Monthly cleaning supplies',
            'Toilet Paper' => 'Toilet paper restock',
            'Hand Soap' => 'Hand soap restock',
            'Cleaning Products' => 'Cleaning products purchase',
            'Paper Towels' => 'Paper towels restock',
            'Social Media Ads' => 'Social media advertising campaign',
            'Flyers' => 'Flyer printing and distribution',
            'Event Sponsorship' => 'Local event sponsorship',
            'Parking Fee' => 'Monthly parking fee',
            'Permit Fee' => 'Business permit renewal',
            'Miscellaneous' => 'Various small expenses',
        ];

        return $descriptions[$subcategory] ?? 'General expense';
    }
}

