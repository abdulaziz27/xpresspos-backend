<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustHosts;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // Check if we're in production with domain routing
            if (app()->environment('production') && env('LANDING_DOMAIN')) {
                // Domain-specific routing for production
                Route::domain(env('LANDING_DOMAIN', 'xpresspos.id'))->middleware(['web', 'domain.routing'])->group(function () {
                    require base_path('routes/landing.php');
                });

                Route::domain(env('OWNER_DOMAIN', 'dashboard.xpresspos.id'))->middleware(['web', 'domain.routing'])->group(function () {
                    require base_path('routes/owner.php');
                });

                Route::domain(env('ADMIN_DOMAIN', 'admin.xpresspos.id'))->middleware(['web', 'domain.routing'])->group(function () {
                    require base_path('routes/admin.php');
                });
            } else {
                // Path-based routing for local development
                // Include landing routes for local development
                Route::middleware(['web'])->group(function () {
                    require base_path('routes/landing.php');
                });
                
                Route::middleware(['web', 'auth'])->prefix('owner')->group(function () {
                    require base_path('routes/owner.php');
                });

                Route::middleware(['web'])->prefix('admin')->group(function () {
                    require base_path('routes/admin.php');
                });
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            PreventRequestsDuringMaintenance::class,
            HandleCors::class,
            TrustProxies::class,
            TrustHosts::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class, // Use custom middleware for API
            'spatie.role' => \Spatie\Permission\Middleware\RoleMiddleware::class, // Keep Spatie for web
            'permission' => \App\Http\Middleware\PermissionMiddleware::class, // Use custom middleware for API
            'spatie.permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class, // Keep Spatie for web
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'tenant.scope' => \App\Http\Middleware\TenantScopeMiddleware::class,
            'domain.routing' => \App\Http\Middleware\DomainRoutingMiddleware::class,
            'store.permission' => \App\Http\Middleware\CheckStorePermission::class,
            'store.context' => \App\Http\Middleware\EnsureStoreContext::class,
            'log.security' => \App\Http\Middleware\LogSecurityEvents::class,
            'api.only' => \App\Http\Middleware\ApiOnlyMiddleware::class,
            'xendit.webhook.security' => \App\Http\Middleware\XenditWebhookSecurity::class,
            'dev.payment' => \App\Http\Middleware\DevelopmentPaymentMiddleware::class,
        ]);

        $middleware->web([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->api([
            SubstituteBindings::class,
            'api.only',
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
