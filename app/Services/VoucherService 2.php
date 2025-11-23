<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Models\Order;
use App\Models\Member;
use App\Models\OrderDiscount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoucherService
{
    /**
     * Validate voucher code and check if it can be redeemed.
     */
    public function validateVoucher(string $code, ?string $tenantId = null, ?Order $order = null): array
    {
        // Find voucher by code
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return [
                'valid' => false,
                'error' => 'Voucher tidak ditemukan',
                'error_code' => 'VOUCHER_NOT_FOUND',
            ];
        }

        // Check tenant
        if ($tenantId && $voucher->tenant_id !== $tenantId) {
            return [
                'valid' => false,
                'error' => 'Voucher tidak valid untuk tenant ini',
                'error_code' => 'VOUCHER_TENANT_MISMATCH',
            ];
        }

        // Check status
        if ($voucher->status !== 'active') {
            return [
                'valid' => false,
                'error' => 'Voucher tidak aktif',
                'error_code' => 'VOUCHER_INACTIVE',
            ];
        }

        // Check date range
        $now = now();
        if ($voucher->valid_from && $voucher->valid_from->isFuture()) {
            return [
                'valid' => false,
                'error' => 'Voucher belum berlaku',
                'error_code' => 'VOUCHER_NOT_STARTED',
                'valid_from' => $voucher->valid_from->toISOString(),
            ];
        }

        if ($voucher->valid_until && $voucher->valid_until->isPast()) {
            return [
                'valid' => false,
                'error' => 'Voucher sudah kadaluarsa',
                'error_code' => 'VOUCHER_EXPIRED',
                'valid_until' => $voucher->valid_until->toISOString(),
            ];
        }

        // Check max redemptions
        $redemptionsCount = $voucher->redemptions()->count();
        if ($voucher->max_redemptions !== null && $redemptionsCount >= $voucher->max_redemptions) {
            return [
                'valid' => false,
                'error' => 'Voucher sudah mencapai batas penggunaan',
                'error_code' => 'VOUCHER_MAX_REDEMPTIONS_REACHED',
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

            // If order is provided, check if promotion applies to order
            if ($order) {
                // Check store
                if (!$promotion->appliesToStore($order->store_id)) {
                    return [
                        'valid' => false,
                        'error' => 'Voucher tidak berlaku untuk toko ini',
                        'error_code' => 'VOUCHER_STORE_MISMATCH',
                    ];
                }
            }
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
     * Calculate discount amount for voucher.
     * If voucher is linked to promotion, use promotion's reward calculation.
     * Otherwise, return null (voucher without promotion cannot provide discount).
     */
    public function calculateDiscount(Voucher $voucher, Order $order): ?float
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
            $discount = $this->calculateRewardDiscount($reward, $order);
            if ($discount > 0) {
                $totalDiscount += $discount;
            }
        }

        // Ensure discount doesn't exceed order subtotal
        return min($totalDiscount, $order->subtotal);
    }

    /**
     * Calculate discount from a single reward.
     */
    protected function calculateRewardDiscount($reward, Order $order): float
    {
        $rewardValue = $reward->reward_value ?? [];
        $rewardType = $reward->reward_type ?? null;

        return match ($rewardType) {
            'PCT_OFF' => $order->subtotal * (($rewardValue['percentage'] ?? 0) / 100),
            'AMOUNT_OFF' => min($rewardValue['amount'] ?? 0, $order->subtotal),
            default => 0,
        };
    }

    /**
     * Redeem voucher for an order.
     */
    public function redeemVoucher(Order $order, string $code, ?Member $member = null): array
    {
        try {
            DB::beginTransaction();

            // Validate voucher
            $validation = $this->validateVoucher($code, $order->tenant_id, $order);

            if (!$validation['valid']) {
                return $validation;
            }

            $voucher = $validation['voucher'];

            // Calculate discount
            $discountAmount = $this->calculateDiscount($voucher, $order);

            if ($discountAmount === null || $discountAmount <= 0) {
                DB::rollBack();
                return [
                    'valid' => false,
                    'error' => 'Voucher tidak memiliki diskon yang dapat diterapkan',
                    'error_code' => 'VOUCHER_NO_DISCOUNT',
                ];
            }

            // Check if voucher already used for this order
            $existingRedemption = VoucherRedemption::where('voucher_id', $voucher->id)
                ->where('order_id', $order->id)
                ->first();

            if ($existingRedemption) {
                DB::rollBack();
                return [
                    'valid' => false,
                    'error' => 'Voucher sudah digunakan untuk order ini',
                    'error_code' => 'VOUCHER_ALREADY_REDEEMED',
                ];
            }

            // Create voucher redemption
            $redemption = VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'member_id' => $member?->id ?? $order->member_id,
                'order_id' => $order->id,
                'redeemed_at' => now(),
                'discount_amount' => $discountAmount,
            ]);

            // Create order discount record
            OrderDiscount::create([
                'order_id' => $order->id,
                'promotion_id' => $voucher->promotion_id,
                'voucher_id' => $voucher->id,
                'discount_type' => 'VOUCHER',
                'discount_amount' => $discountAmount,
            ]);

            // Update order discount amount
            $order->discount_amount = ($order->discount_amount ?? 0) + $discountAmount;
            $order->total_amount = $order->subtotal + ($order->tax_amount ?? 0) + ($order->service_charge ?? 0) - $order->discount_amount;
            $order->save();

            // Increment voucher redemptions count
            $voucher->increment('redemptions_count');

            // Check if voucher should be marked as expired
            $redemptionsCount = $voucher->redemptions()->count();
            if ($voucher->max_redemptions !== null && $redemptionsCount >= $voucher->max_redemptions) {
                $voucher->update(['status' => 'expired']);
            }

            DB::commit();

            Log::info('Voucher redeemed', [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'order_id' => $order->id,
                'discount_amount' => $discountAmount,
                'member_id' => $member?->id ?? $order->member_id,
            ]);

            return [
                'success' => true,
                'voucher' => $voucher,
                'redemption' => $redemption,
                'discount_amount' => $discountAmount,
                'order' => $order->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to redeem voucher', [
                'voucher_code' => $code,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Gagal menggunakan voucher: ' . $e->getMessage(),
                'error_code' => 'REDEMPTION_FAILED',
            ];
        }
    }
}

