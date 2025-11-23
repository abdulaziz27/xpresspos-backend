<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use Illuminate\Console\Command;

class CheckCoupon extends Command
{
    protected $signature = 'coupon:check {code?}';
    protected $description = 'Check coupon code details';

    public function handle()
    {
        $code = $this->argument('code');

        if ($code) {
            // Check specific coupon
            $voucher = Voucher::withoutGlobalScopes()
                ->where('code', strtoupper($code))
                ->with(['promotion' => function ($q) {
                    $q->withoutGlobalScopes()->with(['rewards' => function ($r) {
                        $r->withoutGlobalScopes();
                    }]);
                }])
                ->first();

            if (!$voucher) {
                $this->error("Kupon dengan kode '{$code}' tidak ditemukan.");
                return 1;
            }

            $this->info("=== Detail Kupon: {$voucher->code} ===");
            $this->line("Status: {$voucher->status}");
            $this->line("Valid From: {$voucher->valid_from}");
            $this->line("Valid Until: {$voucher->valid_until}");
            $this->line("Max Redemptions: " . ($voucher->max_redemptions ?? 'Unlimited'));
            $this->line("Used: {$voucher->redemptions_count}");
            
            if ($voucher->promotion) {
                $this->line("Promotion: {$voucher->promotion->name}");
                
                if ($voucher->promotion->rewards->isNotEmpty()) {
                    $this->line("\nRewards:");
                    foreach ($voucher->promotion->rewards as $reward) {
                        if ($reward->reward_type === 'PCT_OFF') {
                            $percentage = $reward->reward_value['percentage'] ?? 0;
                            $this->line("  - Discount: {$percentage}%");
                        } elseif ($reward->reward_type === 'AMOUNT_OFF') {
                            $amount = $reward->reward_value['amount'] ?? 0;
                            $this->line("  - Discount: Rp " . number_format($amount, 0, ',', '.'));
                        }
                    }
                }
            }

            // Validate coupon
            $couponService = app(\App\Services\SubscriptionCouponService::class);
            $result = $couponService->validateCoupon($voucher->code);
            
            $this->line("\n=== Validasi ===");
            if ($result['valid']) {
                $this->info("✓ Kupon VALID");
                $this->line("Redemptions Remaining: " . ($result['redemptions_remaining'] ?? 'Unlimited'));
            } else {
                $this->error("✗ Kupon TIDAK VALID");
                $this->line("Error: " . ($result['error'] ?? 'Unknown error'));
            }
        } else {
            // List all active coupons
            $vouchers = Voucher::withoutGlobalScopes()
                ->where('status', 'active')
                ->where('valid_from', '<=', now())
                ->where('valid_until', '>=', now())
                ->with(['promotion' => function ($q) {
                    $q->withoutGlobalScopes()->with(['rewards' => function ($r) {
                        $r->withoutGlobalScopes();
                    }]);
                }])
                ->get();

            if ($vouchers->isEmpty()) {
                $this->warn("Tidak ada kupon aktif ditemukan.");
                return 0;
            }

            $this->info("=== Daftar Kupon Aktif ===");
            $this->newLine();

            $headers = ['Code', 'Status', 'Valid Until', 'Used/Max', 'Discount'];
            $rows = [];

            foreach ($vouchers as $voucher) {
                $discount = '-';
                if ($voucher->promotion && $voucher->promotion->rewards->isNotEmpty()) {
                    foreach ($voucher->promotion->rewards as $reward) {
                        if ($reward->reward_type === 'PCT_OFF') {
                            $discount = ($reward->reward_value['percentage'] ?? 0) . '%';
                        } elseif ($reward->reward_type === 'AMOUNT_OFF') {
                            $discount = 'Rp ' . number_format($reward->reward_value['amount'] ?? 0, 0, ',', '.');
                        }
                    }
                }

                $rows[] = [
                    $voucher->code,
                    $voucher->status,
                    $voucher->valid_until->format('d M Y'),
                    $voucher->redemptions_count . '/' . ($voucher->max_redemptions ?? '∞'),
                    $discount,
                ];
            }

            $this->table($headers, $rows);
        }

        return 0;
    }
}

