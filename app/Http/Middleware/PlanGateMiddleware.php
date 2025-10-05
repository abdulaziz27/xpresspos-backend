<?php

namespace App\Http\Middleware;

use App\Services\PlanLimitValidationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanGateMiddleware
{
    public function __construct(
        private PlanLimitValidationService $planLimitValidationService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature = null): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Authentication required to access this resource'
                    ]
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        $user = auth()->user();

        // Check if user has store context
        if (!$user->store_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NO_STORE_CONTEXT',
                        'message' => 'Store context required for this operation'
                    ]
                ], 403);
            }

            abort(403, 'Store context required');
        }

        $store = $user->store;

        // Check if store has active subscription
        $subscription = $store->activeSubscription;
        if (!$subscription) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NO_ACTIVE_SUBSCRIPTION',
                        'message' => 'Active subscription required for this operation'
                    ]
                ], 403);
            }

            abort(403, 'Active subscription required');
        }

        // Check if subscription is expired
        if ($subscription->hasExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SUBSCRIPTION_EXPIRED',
                        'message' => 'Subscription has expired'
                    ]
                ], 403);
            }

            abort(403, 'Subscription expired');
        }

        // Check feature availability if specified
        if ($feature) {
            $plan = $subscription->plan;
            if (!$plan->hasFeature($feature)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'PLAN_FEATURE_REQUIRED',
                            'message' => 'Feature not available in current plan',
                            'required_plan' => $plan->getRequiredPlanFor($feature)
                        ]
                    ], 403);
                }

                abort(403, 'Feature not available');
            }
        }

        // Check usage limits
        $actionCheck = $this->planLimitValidationService->canPerformAction($store, $feature ?? 'pos');
        if (!$actionCheck['allowed']) {
            if ($actionCheck['reason'] === 'limit_exceeded') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'PLAN_LIMIT_EXCEEDED',
                            'message' => $actionCheck['message']
                        ]
                    ], 403);
                }

                abort(403, 'Plan limit exceeded');
            }
        }

        // For transactions, always allow (soft cap) but check for warnings
        if ($feature === 'transactions') {
            $quotaCheck = $this->planLimitValidationService->canPerformAction($store, 'transactions');

            if ($quotaCheck['allowed'] && isset($quotaCheck['soft_cap_triggered']) && $quotaCheck['soft_cap_triggered']) {
                $response = $next($request);
                $response->headers->set('X-Quota-Warning', 'Annual transaction quota exceeded');
                return $response;
            }
        }

        // Check if quota is exceeded and block premium features
        // For transaction quota check, we need to check the usage directly since 'transactions' is not a plan feature
        $subscription = $store->activeSubscription;
        $usage = $subscription->usage()->where('feature_type', 'transactions')->first();
        $quotaExceeded = $usage && $usage->annual_quota && $usage->current_usage >= $usage->annual_quota;

        if ($quotaExceeded) {
            // Define premium features that should be blocked when quota is exceeded
            // Note: inventory_tracking is allowed even when quota is exceeded (non-premium feature)
            $premiumFeatures = ['report_export', 'advanced_reports'];

            if (in_array($feature, $premiumFeatures)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'QUOTA_EXCEEDED_PREMIUM_BLOCKED',
                            'message' => 'Premium features are blocked when transaction quota is exceeded'
                        ]
                    ], 403);
                }

                abort(403, 'Premium features blocked when quota exceeded');
            }

            // For non-premium features, add quota warning header
            if (!in_array($feature, $premiumFeatures)) {
                $response = $next($request);
                $response->headers->set('X-Quota-Warning', 'Transaction quota exceeded');
                return $response;
            }
        }


        // Add warning headers if approaching limits for other features
        $usageSummary = $this->planLimitValidationService->getUsageSummary($store);
        if (isset($usageSummary['features'][$feature ?? 'pos'])) {
            $featureSummary = $usageSummary['features'][$feature ?? 'pos'];
            if ($featureSummary['status'] === 'approaching_limit') {
                $response = $next($request);
                $response->headers->set('X-Usage-Warning', 'Approaching plan limits');
                $response->headers->set('X-Usage-Percentage', $featureSummary['usage_percentage']);
                return $response;
            }
        }

        return $next($request);
    }
}
