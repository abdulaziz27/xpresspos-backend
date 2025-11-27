<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    protected VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * Validate voucher code.
     * 
     * POST /api/v1/vouchers/validate
     */
    public function validateVoucher(Request $request): JsonResponse
    {
        // Voucher validation is a read operation, check viewAny permission
        $this->authorize('viewAny', Voucher::class);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'order_id' => 'nullable|uuid|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $code = $request->input('code');
        $orderId = $request->input('order_id');
        $order = null;

        // Get order if provided
        if ($orderId) {
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ORDER_NOT_FOUND',
                        'message' => 'Order tidak ditemukan',
                    ],
                ], 404);
            }
        }

        // Get tenant ID from order or user context
        $tenantId = $order?->tenant_id ?? auth()->user()?->currentTenant()?->id;

        // Validate voucher
        $result = $this->voucherService->validateVoucher($code, $tenantId, $order);

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $result['error_code'] ?? 'VOUCHER_INVALID',
                    'message' => $result['error'] ?? 'Voucher tidak valid',
                ],
                'meta' => array_filter([
                    'valid_from' => $result['valid_from'] ?? null,
                    'valid_until' => $result['valid_until'] ?? null,
                    'max_redemptions' => $result['max_redemptions'] ?? null,
                    'current_count' => $result['current_count'] ?? null,
                ]),
            ], 400);
        }

        // Calculate preview discount if order is provided
        $discountPreview = null;
        if ($order) {
            $discountPreview = $this->voucherService->calculateDiscount($result['voucher'], $order);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'voucher' => [
                    'id' => $result['voucher']->id,
                    'code' => $result['voucher']->code,
                    'promotion_id' => $result['voucher']->promotion_id,
                    'promotion_name' => $result['promotion']?->name,
                    'valid_from' => $result['voucher']->valid_from?->toISOString(),
                    'valid_until' => $result['voucher']->valid_until?->toISOString(),
                    'redemptions_remaining' => $result['redemptions_remaining'],
                ],
                'discount_preview' => $discountPreview,
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ]);
    }

    /**
     * Redeem voucher for an order.
     * 
     * POST /api/v1/vouchers/redeem
     */
    public function redeem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'order_id' => 'required|uuid|exists:orders,id',
            'member_id' => 'nullable|uuid|exists:members,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $code = $request->input('code');
        $orderId = $request->input('order_id');
        $memberId = $request->input('member_id');

        // Get order
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order tidak ditemukan',
                ],
            ], 404);
        }

        // Get member if provided
        $member = $memberId ? \App\Models\Member::find($memberId) : null;

        // Redeem voucher
        $result = $this->voucherService->redeemVoucher($order, $code, $member);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $result['error_code'] ?? 'REDEMPTION_FAILED',
                    'message' => $result['error'] ?? 'Gagal menggunakan voucher',
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'voucher' => [
                    'id' => $result['voucher']->id,
                    'code' => $result['voucher']->code,
                    'promotion_name' => $result['voucher']->promotion?->name,
                ],
                'redemption' => [
                    'id' => $result['redemption']->id,
                    'discount_amount' => $result['discount_amount'],
                    'redeemed_at' => $result['redemption']->redeemed_at->toISOString(),
                ],
                'order' => [
                    'id' => $result['order']->id,
                    'order_number' => $result['order']->order_number,
                    'subtotal' => $result['order']->subtotal,
                    'discount_amount' => $result['order']->discount_amount,
                    'total_amount' => $result['order']->total_amount,
                ],
            ],
            'message' => 'Voucher berhasil digunakan',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ]);
    }

    /**
     * Get active vouchers for current tenant.
     * 
     * GET /api/v1/vouchers
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->currentTenant()?->id;

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_MISSING',
                    'message' => 'Tenant context tidak ditemukan',
                ],
            ], 400);
        }

        $query = Voucher::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->with('promotion');

        // Filter by promotion if provided
        if ($request->has('promotion_id')) {
            $query->where('promotion_id', $request->input('promotion_id'));
        }

        // Filter by store if provided
        if ($request->has('store_id') && $request->input('store_id')) {
            $query->whereHas('promotion', function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->whereNull('store_id')
                        ->orWhere('store_id', $request->input('store_id'));
                });
            });
        }

        $vouchers = $query->get();

        return response()->json([
            'success' => true,
            'data' => $vouchers->map(function ($voucher) {
                $redemptionsCount = $voucher->redemptions()->count();
                return [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'promotion_id' => $voucher->promotion_id,
                    'promotion_name' => $voucher->promotion?->name,
                    'valid_from' => $voucher->valid_from?->toISOString(),
                    'valid_until' => $voucher->valid_until?->toISOString(),
                    'max_redemptions' => $voucher->max_redemptions,
                    'redemptions_count' => $redemptionsCount,
                    'redemptions_remaining' => $voucher->max_redemptions === null 
                        ? null 
                        : max(0, $voucher->max_redemptions - $redemptionsCount),
                    'status' => $voucher->status,
                ];
            }),
            'meta' => [
                'count' => $vouchers->count(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ]);
    }
}

