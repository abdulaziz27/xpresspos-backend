<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberTier;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyService
{
    /**
     * Process loyalty points for a completed order.
     */
    public function processOrderLoyalty(Order $order): void
    {
        if (!$order->member_id || $order->status !== 'completed') {
            return;
        }

        try {
            DB::beginTransaction();

            $member = $order->member;
            $pointsEarned = $member->calculatePointsFromPurchase($order->total_amount);

            if ($pointsEarned > 0) {
                $member->addLoyaltyPoints(
                    $pointsEarned,
                    "Points earned from order #{$order->order_number}",
                    [
                        'order_id' => $order->id,
                        'order_amount' => $order->total_amount,
                        'points_rate' => 1, // 1 point per Rp 1.000
                        'tier_multiplier' => $member->tier?->benefits['points_multiplier'] ?? 1,
                        'user_id' => $order->user_id, // Pass order's user_id as fallback for Observer context
                    ]
                );
            }

            // Update member statistics
            $member->updateStats($order->total_amount);

            DB::commit();

            // Log only in debug mode or for significant point amounts
            if (config('app.debug') || $pointsEarned >= 100) {
                Log::info('Loyalty points processed for order', [
                    'order_id' => $order->id,
                    'member_id' => $member->id,
                    'points_earned' => $pointsEarned,
                    'new_balance' => $member->fresh()->loyalty_points,
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process loyalty points for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Apply loyalty discount to order.
     */
    public function applyLoyaltyDiscount(Order $order, int $pointsToRedeem): array
    {
        if (!$order->member_id) {
            throw new \InvalidArgumentException('Order must have a member to apply loyalty discount');
        }

        $member = $order->member;
        
        if ($member->loyalty_points < $pointsToRedeem) {
            throw new \InvalidArgumentException('Insufficient loyalty points');
        }

        // Calculate discount amount (1 point = $0.01 discount)
        $discountAmount = $pointsToRedeem * 0.01;
        
        // Ensure discount doesn't exceed order total
        $discountAmount = min($discountAmount, $order->subtotal);

        return [
            'points_to_redeem' => $pointsToRedeem,
            'discount_amount' => $discountAmount,
            'remaining_points' => $member->loyalty_points - $pointsToRedeem,
        ];
    }

    /**
     * Redeem loyalty points for discount.
     */
    public function redeemPointsForDiscount(Member $member, int $pointsToRedeem, Order $order = null): bool
    {
        try {
            DB::beginTransaction();

            $success = $member->redeemLoyaltyPoints(
                $pointsToRedeem,
                $order ? "Points redeemed for order #{$order->order_number}" : 'Points redeemed for discount',
                [
                    'order_id' => $order?->id,
                    'discount_amount' => $pointsToRedeem * 0.01,
                    'redemption_rate' => 0.01, // $0.01 per point
                ]
            );

            if ($success) {
                DB::commit();
                return true;
            }

            DB::rollBack();
            return false;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to redeem loyalty points', [
                'member_id' => $member->id,
                'points' => $pointsToRedeem,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Initialize default member tiers for a store.
     */
    public function initializeDefaultTiers(string $storeId): void
    {
        $defaultTiers = [
            [
                'name' => 'Bronze',
                'slug' => 'bronze',
                'min_points' => 0,
                'max_points' => 999,
                'discount_percentage' => 0,
                'benefits' => [
                    'points_multiplier' => 1,
                    'birthday_bonus' => 50,
                    'features' => ['Basic member benefits']
                ],
                'color' => '#CD7F32',
                'sort_order' => 1,
                'description' => 'Welcome tier for new members',
            ],
            [
                'name' => 'Silver',
                'slug' => 'silver',
                'min_points' => 1000,
                'max_points' => 4999,
                'discount_percentage' => 5.00,
                'benefits' => [
                    'points_multiplier' => 1.2,
                    'birthday_bonus' => 100,
                    'features' => ['5% discount', '20% bonus points', 'Priority support']
                ],
                'color' => '#C0C0C0',
                'sort_order' => 2,
                'description' => 'Silver tier with enhanced benefits',
            ],
            [
                'name' => 'Gold',
                'slug' => 'gold',
                'min_points' => 5000,
                'max_points' => 9999,
                'discount_percentage' => 10.00,
                'benefits' => [
                    'points_multiplier' => 1.5,
                    'birthday_bonus' => 200,
                    'features' => ['10% discount', '50% bonus points', 'Exclusive offers', 'Free delivery']
                ],
                'color' => '#FFD700',
                'sort_order' => 3,
                'description' => 'Gold tier with premium benefits',
            ],
            [
                'name' => 'Platinum',
                'slug' => 'platinum',
                'min_points' => 10000,
                'max_points' => null,
                'discount_percentage' => 15.00,
                'benefits' => [
                    'points_multiplier' => 2,
                    'birthday_bonus' => 500,
                    'features' => ['15% discount', '100% bonus points', 'VIP treatment', 'Personal concierge']
                ],
                'color' => '#E5E4E2',
                'sort_order' => 4,
                'description' => 'Platinum tier with VIP benefits',
            ],
        ];

        foreach ($defaultTiers as $tierData) {
            MemberTier::create([
                'store_id' => $storeId,
                ...$tierData
            ]);
        }
    }

    /**
     * Get member tier statistics for a store.
     */
    public function getTierStatistics(string $storeId): array
    {
        $tiers = MemberTier::where('store_id', $storeId)
            ->active()
            ->ordered()
            ->withCount('members')
            ->get();

        $totalMembers = Member::where('store_id', $storeId)->active()->count();

        return $tiers->map(function ($tier) use ($totalMembers) {
            return [
                'tier' => $tier,
                'member_count' => $tier->members_count,
                'percentage' => $totalMembers > 0 ? round(($tier->members_count / $totalMembers) * 100, 2) : 0,
            ];
        })->toArray();
    }

    /**
     * Expire old loyalty points based on store policy.
     */
    public function expireOldPoints(string $storeId, int $expirationDays = 365): int
    {
        $expirationDate = now()->subDays($expirationDays);
        $expiredCount = 0;

        $transactions = LoyaltyPointTransaction::where('store_id', $storeId)
            ->earned()
            ->where('created_at', '<=', $expirationDate)
            ->whereNull('expires_at')
            ->get();

        foreach ($transactions as $transaction) {
            try {
                DB::beginTransaction();

                $member = $transaction->member;
                $pointsToExpire = min($transaction->points, $member->loyalty_points);

                if ($pointsToExpire > 0) {
                    $balanceBefore = $member->loyalty_points;
                    $member->decrement('loyalty_points', $pointsToExpire);
                    $balanceAfter = $member->fresh()->loyalty_points;

                    // Create expiration transaction
                    LoyaltyPointTransaction::create([
                        'store_id' => $storeId,
                        'member_id' => $member->id,
                        'user_id' => null, // System generated
                        'type' => 'expired',
                        'points' => -$pointsToExpire,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'reason' => 'Points expired after ' . $expirationDays . ' days',
                        'metadata' => [
                            'original_transaction_id' => $transaction->id,
                            'expiration_policy_days' => $expirationDays,
                        ],
                    ]);

                    // Mark original transaction as expired
                    $transaction->update(['expires_at' => now()]);
                    
                    // Update member tier
                    $member->updateTier();

                    $expiredCount++;
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to expire loyalty points', [
                    'transaction_id' => $transaction->id,
                    'member_id' => $transaction->member_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $expiredCount;
    }
}