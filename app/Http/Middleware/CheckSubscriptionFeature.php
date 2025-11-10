<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionFeature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is not available in your current plan.',
                    'feature' => $feature,
                    'current_plan' => $user->getSubscriptionTier(),
                    'upgrade_url' => route('landing.pricing'),
                ], 403);
            }

            return redirect()
                ->back()
                ->with('error', 'This feature is not available in your current plan. Please upgrade to access it.');
        }

        return $next($request);
    }
}
