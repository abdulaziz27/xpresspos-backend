<?php

namespace Database\Seeders;

use App\Models\MemberTier;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MemberTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('⚠️ No tenants found. Please run TenantSeeder first.');
            return;
        }

        // Default tiers configuration - realistic and demo-friendly
        $defaultTiers = [
            [
                'name' => 'Bronze',
                'slug' => 'bronze',
                'min_points' => 0,
                'max_points' => 499,
                'discount_percentage' => 0.00,
                'benefits' => [
                    [
                        'title' => 'Welcome Bonus',
                        'details' => 'Dapatkan 50 poin bonus saat ulang tahun'
                    ],
                    [
                        'title' => 'Basic Benefits',
                        'details' => 'Akses ke program loyalitas dasar'
                    ]
                ],
                'color' => '#CD7F32', // Bronze color
                'sort_order' => 1,
                'is_active' => true,
                'description' => 'Tier awal untuk member baru. Mulai kumpulkan poin untuk naik ke tier berikutnya!',
            ],
            [
                'name' => 'Silver',
                'slug' => 'silver',
                'min_points' => 500,
                'max_points' => 1999,
                'discount_percentage' => 5.00,
                'benefits' => [
                    [
                        'title' => 'Diskon 5%',
                        'details' => 'Dapatkan diskon 5% untuk setiap transaksi'
                    ],
                    [
                        'title' => 'Bonus Poin 20%',
                        'details' => 'Dapatkan 20% bonus poin dari setiap pembelian'
                    ],
                    [
                        'title' => 'Birthday Bonus',
                        'details' => 'Dapatkan 100 poin bonus saat ulang tahun'
                    ]
                ],
                'color' => '#C0C0C0', // Silver color
                'sort_order' => 2,
                'is_active' => true,
                'description' => 'Tier Silver memberikan diskon dan bonus poin untuk member setia.',
            ],
            [
                'name' => 'Gold',
                'slug' => 'gold',
                'min_points' => 2000,
                'max_points' => 4999,
                'discount_percentage' => 10.00,
                'benefits' => [
                    [
                        'title' => 'Diskon 10%',
                        'details' => 'Dapatkan diskon 10% untuk setiap transaksi'
                    ],
                    [
                        'title' => 'Bonus Poin 50%',
                        'details' => 'Dapatkan 50% bonus poin dari setiap pembelian'
                    ],
                    [
                        'title' => 'Priority Support',
                        'details' => 'Layanan prioritas untuk member Gold'
                    ],
                    [
                        'title' => 'Birthday Special',
                        'details' => 'Dapatkan 200 poin bonus dan minuman gratis saat ulang tahun'
                    ]
                ],
                'color' => '#FFD700', // Gold color
                'sort_order' => 3,
                'is_active' => true,
                'description' => 'Tier Gold dengan benefit premium dan diskon lebih besar.',
            ],
            [
                'name' => 'Platinum',
                'slug' => 'platinum',
                'min_points' => 5000,
                'max_points' => null, // No upper limit
                'discount_percentage' => 15.00,
                'benefits' => [
                    [
                        'title' => 'Diskon 15%',
                        'details' => 'Dapatkan diskon 15% untuk setiap transaksi'
                    ],
                    [
                        'title' => 'Double Points',
                        'details' => 'Dapatkan 2x poin dari setiap pembelian'
                    ],
                    [
                        'title' => 'VIP Treatment',
                        'details' => 'Layanan VIP dan prioritas maksimal'
                    ],
                    [
                        'title' => 'Exclusive Offers',
                        'details' => 'Akses ke penawaran eksklusif dan event khusus'
                    ],
                    [
                        'title' => 'Birthday VIP',
                        'details' => 'Dapatkan 500 poin bonus dan paket spesial saat ulang tahun'
                    ]
                ],
                'color' => '#E5E4E2', // Platinum color
                'sort_order' => 4,
                'is_active' => true,
                'description' => 'Tier tertinggi dengan benefit VIP dan diskon maksimal. Untuk member paling setia!',
            ],
        ];

        // Create tiers for each tenant
        $tenants->each(function ($tenant) use ($defaultTiers) {
            // Check if tenant already has tiers
            $existingTiers = MemberTier::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count();

            if ($existingTiers > 0) {
                $this->command->info("⏭️  Tenant '{$tenant->name}' already has {$existingTiers} tier(s). Skipping...");
                return;
            }

            $this->command->info("📊 Creating member tiers for tenant: {$tenant->name}");

            foreach ($defaultTiers as $tierData) {
                MemberTier::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'store_id' => null, // Available for all stores
                    'name' => $tierData['name'],
                    'slug' => $tierData['slug'],
                    'min_points' => $tierData['min_points'],
                    'max_points' => $tierData['max_points'],
                    'discount_percentage' => $tierData['discount_percentage'],
                    'benefits' => $tierData['benefits'],
                    'color' => $tierData['color'],
                    'sort_order' => $tierData['sort_order'],
                    'is_active' => $tierData['is_active'],
                    'description' => $tierData['description'],
                ]);

                $this->command->line("   ✓ Created tier: {$tierData['name']} ({$tierData['min_points']} - " . ($tierData['max_points'] ?? '∞') . " points)");
            }

            $this->command->info("✅ Successfully created " . count($defaultTiers) . " tiers for tenant: {$tenant->name}");
        });
    }
}
