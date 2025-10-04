<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Get all payment methods for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $paymentMethods = $user->paymentMethods()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'payment_methods' => $paymentMethods->map(function ($method) {
                    return [
                        'id' => $method->id,
                        'gateway' => $method->gateway,
                        'type' => $method->type,
                        'display_name' => $method->display_name,
                        'masked_number' => $method->masked_number,
                        'is_default' => $method->is_default,
                        'is_usable' => $method->isUsable(),
                        'expires_at' => $method->expires_at?->toISOString(),
                        'created_at' => $method->created_at->toISOString(),
                    ];
                }),
            ],
            'message' => 'Payment methods retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Create payment token for adding new payment method
     */
    public function createToken(Request $request): JsonResponse
    {
        $request->validate([
            'enabled_payments' => 'sometimes|array',
            'enabled_payments.*' => 'string|in:credit_card,bca_va,bni_va,bri_va,mandiri_va,permata_va,other_va,gopay,shopeepay,qris',
        ]);

        try {
            $user = Auth::user();
            $paymentData = $request->only(['enabled_payments']);
            
            $tokenData = $this->paymentService->createPaymentToken($user, $paymentData);

            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $tokenData['snap_token'],
                    'redirect_url' => $tokenData['redirect_url'],
                ],
                'message' => 'Payment token created successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_CREATION_FAILED',
                    'message' => $e->getMessage(),
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 422);
        }
    }

    /**
     * Save payment method from callback
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'payment_data' => 'required|array',
            'set_as_default' => 'sometimes|boolean',
        ]);

        try {
            $user = Auth::user();
            $paymentData = $request->input('payment_data');
            $setAsDefault = $request->boolean('set_as_default', false);

            $paymentMethod = $this->paymentService->savePaymentMethod($user, $paymentData, $setAsDefault);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => [
                        'id' => $paymentMethod->id,
                        'gateway' => $paymentMethod->gateway,
                        'type' => $paymentMethod->type,
                        'display_name' => $paymentMethod->display_name,
                        'masked_number' => $paymentMethod->masked_number,
                        'is_default' => $paymentMethod->is_default,
                        'is_usable' => $paymentMethod->isUsable(),
                        'expires_at' => $paymentMethod->expires_at?->toISOString(),
                    ],
                ],
                'message' => 'Payment method saved successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_METHOD_SAVE_FAILED',
                    'message' => $e->getMessage(),
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 422);
        }
    }

    /**
     * Set payment method as default
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        // Ensure payment method belongs to authenticated user
        if ($paymentMethod->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_METHOD_NOT_FOUND',
                    'message' => 'Payment method not found or does not belong to you',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $paymentMethod->setAsDefault();

        return response()->json([
            'success' => true,
            'data' => [
                'payment_method' => [
                    'id' => $paymentMethod->id,
                    'is_default' => $paymentMethod->fresh()->is_default,
                ],
            ],
            'message' => 'Payment method set as default successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Delete payment method
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        // Ensure payment method belongs to authenticated user
        if ($paymentMethod->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_METHOD_NOT_FOUND',
                    'message' => 'Payment method not found or does not belong to you',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        try {
            $success = $this->paymentService->deletePaymentMethod($paymentMethod);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Payment method deleted successfully',
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1',
                        'request_id' => $request->header('X-Request-ID', uniqid()),
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYMENT_METHOD_DELETE_FAILED',
                        'message' => 'Failed to delete payment method',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1',
                        'request_id' => $request->header('X-Request-ID', uniqid()),
                    ]
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_METHOD_DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 422);
        }
    }
}