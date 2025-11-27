<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     * We redirect to login page instead, which will then redirect based on user role.
     *
     * @var string
     */
    public const HOME = '/login';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Custom rate limiter for Xendit webhooks
        RateLimiter::for('xendit-webhook', function (Request $request) {
            $maxAttempts = config('xendit.security.rate_limit.max_attempts', 60);
            $decayMinutes = config('xendit.security.rate_limit.decay_minutes', 1);
            
            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by('xendit-webhook:' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    // Log rate limit exceeded for security monitoring
                    app(\App\Services\PaymentSecurityService::class)->logWebhookSecurityEvent(
                        'rate_limit_exceeded',
                        $request,
                        ['max_attempts' => config('xendit.security.rate_limit.max_attempts', 60)]
                    );
                    
                    return response()->json([
                        'error' => 'Rate limit exceeded',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429, $headers);
                });
        });

    }
}
