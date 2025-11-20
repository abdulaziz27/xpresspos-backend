<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    // NOTE: PaymentService (Midtrans) telah dihapus karena tidak digunakan.
    // Fitur payment method setup perlu di-refactor untuk Xendit jika diperlukan.

    /**
     * Get all payment methods for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentMethod::class);
        
        $user = auth()->user() ?? request()->user();

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
     * 
     * NOTE: Fitur ini menggunakan Midtrans yang telah dihapus.
     * Perlu di-refactor untuk Xendit jika diperlukan.
     */
    public function createToken(Request $request): JsonResponse
    {
            return response()->json([
                'success' => false,
                'error' => [
                'code' => 'FEATURE_NOT_AVAILABLE',
                'message' => 'Payment method setup feature is not available. This feature used Midtrans which has been removed.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
        ], 501);
    }

    /**
     * Save payment method from callback
     * 
     * NOTE: Fitur ini menggunakan Midtrans yang telah dihapus.
     * Perlu di-refactor untuk Xendit jika diperlukan.
     */
    public function store(Request $request): JsonResponse
    {
                return response()->json([
                    'success' => false,
                    'error' => [
                'code' => 'FEATURE_NOT_AVAILABLE',
                'message' => 'Payment method setup feature is not available. This feature used Midtrans which has been removed.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1',
                        'request_id' => $request->header('X-Request-ID', uniqid()),
                    ]
        ], 501);
    }

    /**
     * Set payment method as default
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('update', $paymentMethod);
        
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
        $this->authorize('delete', $paymentMethod);
        
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

        // NOTE: PaymentService (Midtrans) telah dihapus.
        // Hapus payment method langsung dari database.
        try {
            $paymentMethod->delete();

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
