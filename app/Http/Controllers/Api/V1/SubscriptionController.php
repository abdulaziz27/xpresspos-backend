<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Get current subscription for authenticated user's store
     */
    public function index(Request $request): JsonResponse
    {
        $store = Auth::user()->store;
        
        if (!$store) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_NOT_FOUND',
                    'message' => 'User is not associated with any store',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $subscription = $store->subscriptions()
            ->with(['plan', 'usage', 'invoices' => function ($query) {
                $query->latest()->limit(5);
            }])
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'No active subscription found for this store',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => [
                    'id' => $subscription->id,
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'slug' => $subscription->plan->slug,
                        'price' => $subscription->plan->price,
                        'annual_price' => $subscription->plan->annual_price,
                        'features' => $subscription->plan->features,
                        'limits' => $subscription->plan->limits,
                    ],
                    'status' => $subscription->status,
                    'billing_cycle' => $subscription->billing_cycle,
                    'amount' => $subscription->amount,
                    'starts_at' => $subscription->starts_at->toISOString(),
                    'ends_at' => $subscription->ends_at->toISOString(),
                    'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                    'is_active' => $subscription->isActive(),
                    'on_trial' => $subscription->onTrial(),
                    'days_until_expiration' => $subscription->daysUntilExpiration(),
                    'usage' => $this->subscriptionService->getUsageSummary($subscription),
                    'recent_invoices' => $subscription->invoices->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'amount' => $invoice->total_amount,
                            'status' => $invoice->status,
                            'due_date' => $invoice->due_date->toISOString(),
                            'paid_at' => $invoice->paid_at?->toISOString(),
                        ];
                    }),
                ],
            ],
            'message' => 'Subscription retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Get subscription usage details
     */
    public function usage(Request $request): JsonResponse
    {
        $store = Auth::user()->store;
        $subscription = $store->subscriptions()->where('status', 'active')->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'No active subscription found',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $usageSummary = $this->subscriptionService->getUsageSummary($subscription);

        return response()->json([
            'success' => true,
            'data' => [
                'usage' => $usageSummary,
                'plan_limits' => $subscription->plan->limits,
                'subscription_year' => [
                    'start' => $subscription->usage->first()?->subscription_year_start?->toISOString(),
                    'end' => $subscription->usage->first()?->subscription_year_end?->toISOString(),
                ],
            ],
            'message' => 'Usage data retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Get subscription status
     */
    public function status(Request $request): JsonResponse
    {
        $store = Auth::user()->store;
        $subscription = $store->subscriptions()->where('status', 'active')->first();

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_subscription' => false,
                    'status' => 'no_subscription',
                    'message' => 'No active subscription found',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ]);
        }

        // Check and update subscription status
        $subscription = $this->subscriptionService->checkSubscriptionStatus($subscription);

        return response()->json([
            'success' => true,
            'data' => [
                'has_subscription' => true,
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
                'on_trial' => $subscription->onTrial(),
                'has_expired' => $subscription->hasExpired(),
                'days_until_expiration' => $subscription->daysUntilExpiration(),
                'plan' => [
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                ],
                'billing_cycle' => $subscription->billing_cycle,
                'ends_at' => $subscription->ends_at->toISOString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            ],
            'message' => 'Subscription status retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Upgrade subscription to a new plan
     */
    public function upgrade(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'sometimes|in:monthly,annual',
        ]);

        $store = Auth::user()->store;
        $subscription = $store->subscriptions()->where('status', 'active')->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'No active subscription found to upgrade',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $newPlan = Plan::find($request->plan_id);

        // Validate upgrade (can't downgrade via this endpoint)
        if ($newPlan->price <= $subscription->plan->price) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_UPGRADE',
                    'message' => 'Cannot upgrade to a plan with lower or equal price. Use downgrade endpoint instead.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 422);
        }

        try {
            $upgradedSubscription = $this->subscriptionService->upgradeSubscription($subscription, $newPlan);

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'id' => $upgradedSubscription->id,
                        'plan' => [
                            'name' => $upgradedSubscription->plan->name,
                            'slug' => $upgradedSubscription->plan->slug,
                        ],
                        'status' => $upgradedSubscription->status,
                        'billing_cycle' => $upgradedSubscription->billing_cycle,
                        'amount' => $upgradedSubscription->amount,
                        'ends_at' => $upgradedSubscription->ends_at->toISOString(),
                    ],
                ],
                'message' => 'Subscription upgraded successfully',
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
                    'code' => 'UPGRADE_FAILED',
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
     * Downgrade subscription to a new plan
     */
    public function downgrade(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $store = Auth::user()->store;
        $subscription = $store->subscriptions()->where('status', 'active')->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'No active subscription found to downgrade',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $newPlan = Plan::find($request->plan_id);

        // Validate downgrade (can't upgrade via this endpoint)
        if ($newPlan->price >= $subscription->plan->price) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_DOWNGRADE',
                    'message' => 'Cannot downgrade to a plan with higher or equal price. Use upgrade endpoint instead.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 422);
        }

        try {
            $downgradedSubscription = $this->subscriptionService->downgradeSubscription($subscription, $newPlan);

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'id' => $downgradedSubscription->id,
                        'current_plan' => [
                            'name' => $downgradedSubscription->plan->name,
                            'slug' => $downgradedSubscription->plan->slug,
                        ],
                        'scheduled_downgrade' => $downgradedSubscription->metadata['scheduled_downgrade'] ?? null,
                        'status' => $downgradedSubscription->status,
                        'ends_at' => $downgradedSubscription->ends_at->toISOString(),
                    ],
                ],
                'message' => 'Subscription downgrade scheduled successfully. Changes will take effect at the end of current billing period.',
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
                    'code' => 'DOWNGRADE_FAILED',
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
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'immediately' => 'sometimes|boolean',
            'reason' => 'sometimes|string|max:500',
        ]);

        $store = Auth::user()->store;
        $subscription = $store->subscriptions()->where('status', 'active')->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'No active subscription found to cancel',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $immediately = $request->boolean('immediately', false);
        $reason = $request->input('reason');

        try {
            $cancelledSubscription = $this->subscriptionService->cancelSubscription($subscription, $immediately);

            // Add cancellation reason to metadata
            if ($reason) {
                $metadata = $cancelledSubscription->metadata ?? [];
                $metadata['cancellation_reason'] = $reason;
                $cancelledSubscription->update(['metadata' => $metadata]);
            }

            $message = $immediately 
                ? 'Subscription cancelled immediately' 
                : 'Subscription cancellation scheduled. Access will continue until the end of current billing period.';

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'id' => $cancelledSubscription->id,
                        'status' => $cancelledSubscription->status,
                        'cancelled_immediately' => $immediately,
                        'ends_at' => $cancelledSubscription->ends_at->toISOString(),
                        'cancellation_effective_date' => $cancelledSubscription->metadata['cancellation_effective_date'] ?? null,
                    ],
                ],
                'message' => $message,
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
                    'code' => 'CANCELLATION_FAILED',
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
     * Renew subscription
     */
    public function renew(Request $request): JsonResponse
    {
        $store = Auth::user()->store;
        $subscription = $store->subscriptions()
            ->whereIn('status', ['active', 'expired'])
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_SUBSCRIPTION_FOUND',
                    'message' => 'No subscription found to renew',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        try {
            $renewedSubscription = $this->subscriptionService->renewSubscription($subscription);

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'id' => $renewedSubscription->id,
                        'status' => $renewedSubscription->status,
                        'billing_cycle' => $renewedSubscription->billing_cycle,
                        'amount' => $renewedSubscription->amount,
                        'starts_at' => $renewedSubscription->starts_at->toISOString(),
                        'ends_at' => $renewedSubscription->ends_at->toISOString(),
                    ],
                ],
                'message' => 'Subscription renewed successfully',
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
                    'code' => 'RENEWAL_FAILED',
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