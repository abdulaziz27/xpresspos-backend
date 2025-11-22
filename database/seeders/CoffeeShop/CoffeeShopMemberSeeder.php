<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Member;
use App\Models\MemberTier;
use App\Models\Store;

class CoffeeShopMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic members for coffee shop.
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
        
        // Use first store as primary store for member registration
        $primaryStore = $stores->first();

        // Get member tiers
        $tiers = MemberTier::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('min_points')
            ->get();

        $bronzeTier = $tiers->where('slug', 'bronze')->first();
        $silverTier = $tiers->where('slug', 'silver')->first();
        $goldTier = $tiers->where('slug', 'gold')->first();
        $platinumTier = $tiers->where('slug', 'platinum')->first();

        $members = [
            [
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@example.com',
                'phone' => '+62811111111',
                'date_of_birth' => '1990-05-15',
                'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'loyalty_points' => 3500,
                'total_spent' => 3500000,
                'visit_count' => 85,
                'last_visit_at' => now()->subDays(2),
                'tier_id' => $goldTier->id ?? null,
                'is_active' => true,
            ],
            [
                'name' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@example.com',
                'phone' => '+62812222222',
                'date_of_birth' => '1985-08-20',
                'address' => 'Jl. Gatot Subroto No. 45, Jakarta Selatan',
                'loyalty_points' => 6500,
                'total_spent' => 6500000,
                'visit_count' => 120,
                'last_visit_at' => now()->subDays(1),
                'tier_id' => $platinumTier->id ?? null,
                'is_active' => true,
            ],
            [
                'name' => 'Ahmad Yani',
                'email' => 'ahmad.yani@example.com',
                'phone' => '+62813333333',
                'date_of_birth' => '1995-03-10',
                'address' => 'Jl. Kemang Raya No. 88, Jakarta Selatan',
                'loyalty_points' => 1200,
                'total_spent' => 1200000,
                'visit_count' => 35,
                'last_visit_at' => now()->subDays(5),
                'tier_id' => $silverTier->id ?? null,
                'is_active' => true,
            ],
            [
                'name' => 'Lisa Sari',
                'email' => 'lisa.sari@example.com',
                'phone' => '+62814444444',
                'date_of_birth' => '1992-11-25',
                'address' => 'Jl. Thamrin No. 67, Jakarta Pusat',
                'loyalty_points' => 450,
                'total_spent' => 450000,
                'visit_count' => 12,
                'last_visit_at' => now()->subDays(7),
                'tier_id' => $bronzeTier->id ?? null,
                'is_active' => true,
            ],
            [
                'name' => 'Dedi Kurniawan',
                'email' => 'dedi.kurniawan@example.com',
                'phone' => '+62815555555',
                'date_of_birth' => '1988-07-05',
                'address' => 'Jl. Rasuna Said No. 99, Jakarta Selatan',
                'loyalty_points' => 850,
                'total_spent' => 850000,
                'visit_count' => 25,
                'last_visit_at' => now()->subDays(3),
                'tier_id' => $bronzeTier->id ?? null,
                'is_active' => true,
            ],
            [
                'name' => 'Maya Indira',
                'email' => 'maya.indira@example.com',
                'phone' => '+62816666666',
                'date_of_birth' => '1993-09-18',
                'address' => 'Jl. HR Rasuna Said No. 12, Jakarta Selatan',
                'loyalty_points' => 2800,
                'total_spent' => 2800000,
                'visit_count' => 70,
                'last_visit_at' => now()->subHours(6),
                'tier_id' => $goldTier->id ?? null,
                'is_active' => true,
            ],
            [
                'name' => 'Rizki Pratama',
                'email' => 'rizki.pratama@example.com',
                'phone' => '+62817777777',
                'date_of_birth' => '1997-01-30',
                'address' => 'Jl. Senopati No. 34, Jakarta Selatan',
                'loyalty_points' => 180,
                'total_spent' => 180000,
                'visit_count' => 5,
                'last_visit_at' => now()->subDays(10),
                'tier_id' => $bronzeTier->id ?? null,
                'is_active' => true,
            ],
            [
                'name' => 'Fitri Ayu',
                'email' => 'fitri.ayu@example.com',
                'phone' => '+62818888888',
                'date_of_birth' => '1991-06-22',
                'address' => 'Jl. Cikini Raya No. 56, Jakarta Pusat',
                'loyalty_points' => 5100,
                'total_spent' => 5100000,
                'visit_count' => 110,
                'last_visit_at' => now()->subHours(2),
                'tier_id' => $platinumTier->id ?? null,
                'is_active' => true,
            ],
        ];

        foreach ($members as $index => $memberData) {
            // Check if member already exists by email
            $existingMember = Member::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('email', $memberData['email'])
                ->first();
            
            if ($existingMember) {
                continue; // Skip if member already exists
            }
            
            // Distribute members across stores (some members register at different stores)
            $memberStore = rand(0, 100) < 70 ? $primaryStore : $stores->random(); // 70% at primary store
            
            // Generate a unique member_number for seeder (using index to ensure uniqueness)
            $date = now()->format('Ymd');
            $sequence = str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            $memberNumber = 'MBR' . $date . $sequence;
            
            // Check if this member_number already exists
            $existingByNumber = Member::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('member_number', $memberNumber)
                ->first();
            
            if ($existingByNumber) {
                // If exists, generate a different one
                $memberNumber = 'MBR' . $date . str_pad(9000 + $index, 4, '0', STR_PAD_LEFT);
            }
            
            $member = Member::query()->withoutGlobalScopes()->create(
                array_merge($memberData, [
                    'tenant_id' => $tenantId,
                    'store_id' => $memberStore->id,
                    'member_number' => $memberNumber, // Set explicitly to avoid auto-generation conflict
                ])
            );

            // Update tier based on points (jika belum sesuai)
            if ($member->tier_id) {
                $member->updateTier();
            }
        }

        $this->command->info('✅ Coffee shop members created successfully!');
    }
}

