<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionCouponService;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionCouponController extends Controller
{
    protected $couponService;

    public function __construct(SubscriptionCouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * Validate coupon code for subscription checkout.
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:255',
            'plan_id' => 'nullable|exists:plans,id',
            'billing_cycle' => 'nullable|in:monthly,yearly',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $code = strtoupper(trim($request->code));
        $tenantId = null;

        // Get tenant if user is authenticated
        if (Auth::check()) {
            $tenant = Auth::user()->currentTenant();
            $tenantId = $tenant?->id;
        }

        // Get plan if provided
        $plan = null;
        if ($request->plan_id) {
            $plan = Plan::find($request->plan_id);
        }

        // Calculate subscription amount
        $subscriptionAmount = $request->amount;
        if (!$subscriptionAmount && $plan) {
            $subscriptionAmount = $request->billing_cycle === 'yearly' 
                ? $plan->annual_price 
                : $plan->price;
        }

        if (!$subscriptionAmount || $subscriptionAmount <= 0) {
            return response()->json([
                'valid' => false,
                'error' => 'Jumlah subscription tidak valid',
                'error_code' => 'INVALID_AMOUNT',
            ], 400);
        }

        try {
            // Validate and calculate discount
            $result = $this->couponService->validateAndCalculate(
                $code,
                $subscriptionAmount,
                $tenantId,
                $plan
            );

            if (!$result['valid']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'valid' => true,
                'code' => $code,
                'discount_amount' => $result['discount_amount'],
                'original_amount' => $result['original_amount'],
                'final_amount' => $result['final_amount'],
                'discount_percentage' => round($result['discount_percentage'], 2),
                'promotion_name' => $result['promotion']->name ?? null,
                'voucher_id' => $result['voucher']->id,
                'redemptions_remaining' => $result['redemptions_remaining'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Coupon validation error', [
                'code' => $code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'valid' => false,
                'error' => 'Terjadi kesalahan saat memvalidasi kupon: ' . $e->getMessage(),
                'error_code' => 'VALIDATION_ERROR',
            ], 500);
        }
    }
}

