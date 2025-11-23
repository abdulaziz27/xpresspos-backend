<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\Promotion;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;

class SubscriptionCouponService
{
    /**
     * Validate voucher code for subscription checkout.
     */
    public function validateCoupon(string $code, ?string $tenantId = null, ?Plan $plan = null): array
    {
        // Find voucher by code (without tenant scope for global vouchers)
        $voucher = Voucher::withoutGlobalScopes()
            ->with(['promotion' => function ($query) {
                $query->withoutGlobalScopes()->with(['rewards' => function ($q) {
                    $q->withoutGlobalScopes();
                }]);
            }])
            ->where('code', $code)
            ->first();

        if (!$voucher) {
            return [
                'valid' => false,
                'error' => 'Kode kupon tidak ditemukan',
                'error_code' => 'COUPON_NOT_FOUND',
            ];
        }

        // Check status
        if ($voucher->status !== 'active') {
            return [
                'valid' => false,
                'error' => 'Kupon tidak aktif',
                'error_code' => 'COUPON_INACTIVE',
            ];
        }

        // Check date range
        $now = now();
        if ($voucher->valid_from && $voucher->valid_from->isFuture()) {
            return [
                'valid' => false,
                'error' => 'Kupon belum berlaku',
                'error_code' => 'COUPON_NOT_STARTED',
                'valid_from' => $voucher->valid_from->toISOString(),
            ];
        }

        if ($voucher->valid_until && $voucher->valid_until->isPast()) {
            return [
                'valid' => false,
                'error' => 'Kupon sudah kadaluarsa',
                'error_code' => 'COUPON_EXPIRED',
                'valid_until' => $voucher->valid_until->toISOString(),
            ];
        }

        // Check max redemptions
        $redemptionsCount = $voucher->redemptions()->withoutGlobalScopes()->count();
        if ($voucher->max_redemptions !== null && $redemptionsCount >= $voucher->max_redemptions) {
            return [
                'valid' => false,
                'error' => 'Kupon sudah mencapai batas penggunaan',
                'error_code' => 'COUPON_MAX_REDEMPTIONS_REACHED',
                'max_redemptions' => $voucher->max_redemptions,
                'current_count' => $redemptionsCount,
            ];
        }

        // If voucher is linked to a promotion, check if promotion is valid
        if ($voucher->promotion_id) {
            $promotion = $voucher->promotion;
            if (!$promotion || !$promotion->isValid()) {
                return [
                    'valid' => false,
                    'error' => 'Promo terkait tidak aktif',
                    'error_code' => 'PROMOTION_INACTIVE',
                ];
            }

            // Check if promotion has rewards
            if ($promotion->rewards->isEmpty()) {
                return [
                    'valid' => false,
                    'error' => 'Kupon tidak memiliki diskon yang dapat diterapkan',
                    'error_code' => 'COUPON_NO_REWARD',
                ];
            }
        } else {
            return [
                'valid' => false,
                'error' => 'Kupon tidak memiliki promo terkait',
                'error_code' => 'COUPON_NO_PROMOTION',
            ];
        }

        return [
            'valid' => true,
            'voucher' => $voucher,
            'promotion' => $voucher->promotion,
            'redemptions_remaining' => $voucher->max_redemptions === null 
                ? null 
                : max(0, $voucher->max_redemptions - $redemptionsCount),
        ];
    }

    /**
     * Calculate discount amount for subscription using voucher.
     */
    public function calculateDiscount(Voucher $voucher, float $subscriptionAmount): ?float
    {
        // Voucher must be linked to a promotion to provide discount
        if (!$voucher->promotion_id || !$voucher->promotion) {
            return null;
        }

        $promotion = $voucher->promotion;

        // Check if promotion has rewards
        if ($promotion->rewards->isEmpty()) {
            return null;
        }

        // Calculate discount from promotion rewards
        $totalDiscount = 0;
        foreach ($promotion->rewards as $reward) {
            $discount = $this->calculateRewardDiscount($reward, $subscriptionAmount);
            if ($discount > 0) {
                $totalDiscount += $discount;
            }
        }

        // Ensure discount doesn't exceed subscription amount
        return min($totalDiscount, $subscriptionAmount);
    }

    /**
     * Calculate discount from a single reward.
     */
    protected function calculateRewardDiscount($reward, float $amount): float
    {
        $rewardValue = $reward->reward_value ?? [];
        $rewardType = $reward->reward_type ?? null;

        return match ($rewardType) {
            'PCT_OFF' => $amount * (($rewardValue['percentage'] ?? 0) / 100),
            'AMOUNT_OFF' => min($rewardValue['amount'] ?? 0, $amount),
            default => 0,
        };
    }

    /**
     * Validate and calculate discount for subscription checkout.
     */
    public function validateAndCalculate(string $code, float $subscriptionAmount, ?string $tenantId = null, ?Plan $plan = null): array
    {
        $validation = $this->validateCoupon($code, $tenantId, $plan);

        if (!$validation['valid']) {
            return $validation;
        }

        $voucher = $validation['voucher'];
        $discountAmount = $this->calculateDiscount($voucher, $subscriptionAmount);

        if ($discountAmount === null || $discountAmount <= 0) {
            return [
                'valid' => false,
                'error' => 'Kupon tidak memiliki diskon yang dapat diterapkan',
                'error_code' => 'COUPON_NO_DISCOUNT',
            ];
        }

        $finalAmount = max(0, $subscriptionAmount - $discountAmount);

        return [
            'valid' => true,
            'voucher' => $voucher,
            'promotion' => $voucher->promotion,
            'discount_amount' => $discountAmount,
            'original_amount' => $subscriptionAmount,
            'final_amount' => $finalAmount,
            'discount_percentage' => $subscriptionAmount > 0 ? ($discountAmount / $subscriptionAmount) * 100 : 0,
            'redemptions_remaining' => $validation['redemptions_remaining'],
        ];
    }
}

