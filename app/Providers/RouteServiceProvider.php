<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

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

        $domains = config('domains', []);

        $this->routes(function () use ($domains) {
            $this->mapApiRoutes($domains['api'] ?? null);
            $this->mapLandingRoutes($domains['landing'] ?? null);
            $this->mapOwnerRoutes($domains['owner'] ?? null);
        });
    }

    protected function mapApiRoutes(?string $domain): void
    {
        $registrar = Route::middleware('api');

        if ($this->shouldUseDomain($domain)) {
            $registrar->domain($domain)->group(base_path('routes/api.php'));
        } else {
            $registrar->prefix('api')->group(base_path('routes/api.php'));
        }
    }

    protected function mapLandingRoutes(?string $domain): void
    {
        $registrar = Route::middleware('web');

        if ($this->shouldUseDomain($domain)) {
            $registrar->domain($domain)->group(base_path('routes/web.php'));
        } else {
            $registrar->group(base_path('routes/web.php'));
        }
    }

    protected function mapOwnerRoutes(?string $domain): void
    {
        $registrar = Route::middleware(['web', 'auth']);

        if ($this->shouldUseDomain($domain)) {
            $registrar->domain($domain)->group(base_path('routes/owner.php'));
        } else {
            $registrar->prefix('owner')->group(base_path('routes/owner.php'));
        }
    }

    protected function shouldUseDomain(?string $domain): bool
    {
        if (blank($domain)) {
            return false;
        }

        $normalized = trim($domain);

        return ! Str::contains($normalized, ['localhost', '127.0.0.1']);
    }
}
