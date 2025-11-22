<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Voucher;
use App\Models\Promotion;
use App\Models\PromotionReward;

echo "=== DAFTAR KODE KUPON ===\n\n";

$vouchers = Voucher::withoutGlobalScopes()
    ->with(['promotion' => function($q) {
        $q->withoutGlobalScopes()->with(['rewards' => function($q2) {
            $q2->withoutGlobalScopes();
        }]);
    }])
    ->get();

if ($vouchers->isEmpty()) {
    echo "Tidak ada kupon yang ditemukan.\n";
    echo "Jalankan: php artisan db:seed --class=SubscriptionCouponSeeder\n";
} else {
    foreach ($vouchers as $v) {
        echo "Kode: " . $v->code . "\n";
        echo "Status: " . $v->status . "\n";
        echo "Valid From: " . $v->valid_from . "\n";
        echo "Valid Until: " . $v->valid_until . "\n";
        echo "Redemptions: " . $v->redemptions_count . "/" . $v->max_redemptions . "\n";
        
        if ($v->promotion) {
            echo "Promotion: " . $v->promotion->name . "\n";
            
            if ($v->promotion->rewards->count() > 0) {
                $reward = $v->promotion->rewards->first();
                if ($reward->reward_type === 'PCT_OFF') {
                    echo "Diskon: " . $reward->reward_value['percentage'] . "%\n";
                } elseif ($reward->reward_type === 'AMOUNT_OFF') {
                    echo "Diskon: Rp " . number_format($reward->reward_value['amount'], 0, ',', '.') . "\n";
                }
            }
        } else {
            echo "Promotion: (tidak ada)\n";
        }
        
        echo "---\n\n";
    }
}

