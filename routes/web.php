<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\AddOnPaymentRedirectController;

$landingRoutes = function () {
    Route::get('/', [LandingController::class, 'index'])->name('landing.home');
    Route::get('/', [LandingController::class, 'index'])->name('landing.main'); // Alias for compatibility
    
    // Auth routes untuk landing (redirect ke owner dashboard setelah login)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LandingController::class, 'showLogin'])->name('landing.login');
        Route::post('/login', [LandingController::class, 'login'])->name('landing.login.post');
        Route::get('/register', [LandingController::class, 'showRegister'])->name('landing.register');
        Route::post('/register', [LandingController::class, 'register'])->name('landing.register.post');
        Route::get('/forgot-password', function () {
            return view('landing.auth.forgot-password');
        })->name('landing.forgot-password');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LandingController::class, 'logout'])->name('landing.logout');
        // Redirect authenticated users to owner dashboard
        Route::get('/dashboard', function () {
            return redirect()->to(config('app.owner_url') . '/dashboard');
        })->name('landing.dashboard.redirect');
    });

    // Subscription and payment routes
    Route::get('/pricing', [LandingController::class, 'showPricing'])->name('landing.pricing');

    // Multi-step checkout (auth required)
    Route::middleware('auth')->group(function () {
        Route::get('/checkout', [LandingController::class, 'showCheckout'])->name('landing.checkout');
        Route::post('/subscription', [LandingController::class, 'processSubscription'])->name('landing.subscription.process');
        Route::get('/checkout/business-info', [LandingController::class, 'showCheckoutStep2'])->name('landing.checkout.step2');
        Route::post('/checkout/business-info', [LandingController::class, 'processCheckoutStep2'])->name('landing.checkout.step2.process');
        Route::get('/checkout/payment-method', [LandingController::class, 'showCheckoutStep3'])->name('landing.checkout.step3');
        Route::post('/checkout/payment-method', [LandingController::class, 'processCheckoutStep3'])->name('landing.checkout.step3.process');
    });
    
    // Payment result pages
    Route::get('/payment/success', [LandingController::class, 'paymentSuccess'])->name('landing.payment.success');
    Route::get('/payment/failed', [LandingController::class, 'paymentFailed'])->name('landing.payment.failed');
    Route::get('/payment/addon/success', AddOnPaymentRedirectController::class)->name('payment.addon.success');
    Route::post('/trial/start', [LandingController::class, 'startTrial'])->name('landing.trial.start');
    Route::get('/customer-dashboard', [LandingController::class, 'customerDashboard'])->name('landing.customer.dashboard');
    Route::get('/customer-login', function () {
        return view('landing.customer-login');
    })->name('landing.customer.login');
    
    // Legacy payment routes (keep for backward compatibility)
    Route::get('/payment', [LandingController::class, 'showPayment'])->name('landing.payment');
    Route::post('/payment/process', [LandingController::class, 'processPayment'])->name('landing.payment.process');
    
    // Cart route
    Route::get('/cart', function () {
        return view('landing.cart');
    })->name('cart');
    
    Route::get('/company', function () {
        return view('company', [
            'title' => 'Company - XpressPOS'
        ]);
    })->name('company');
    
    // Policy pages
    Route::get('/privacy-policy', [LandingController::class, 'showPrivacyPolicy'])->name('landing.privacy-policy');
    Route::get('/terms-and-conditions', [LandingController::class, 'showTermsAndConditions'])->name('landing.terms-and-conditions');
    Route::get('/cookie-policy', [LandingController::class, 'showCookiePolicy'])->name('landing.cookie-policy');
    
    // Coupon validation for checkout (public route)
    Route::post('/checkout/validate-coupon', [\App\Http\Controllers\SubscriptionCouponController::class, 'validateCoupon'])->name('landing.checkout.validate-coupon');
};

// Main domain routing (xpresspos.id) or local fallback
if (app()->environment('local')) {
    Route::group([], $landingRoutes);
} else {
    Route::domain(config('domains.main'))->group($landingRoutes);
}

// Local development domains
if (app()->environment('local')) {
    Route::domain(config('domains.local.main'))->group(function () {
        Route::get('/', [LandingController::class, 'index'])->name('landing.main.local');
    });
}

// Test route for debugging
Route::get('/test-navbar', function () {
    return view('landing.xpresspos', [
        'title' => 'XpressPOS - AI Maksimalkan Bisnismu'
    ]);
});

// API domain routing (api.xpresspos.id) - should return JSON
Route::domain(config('domains.api'))->group(function () {
    Route::get('/', function () {
        return response()->json([
            'message' => 'XpressPOS API',
            'version' => '1.0',
            'status' => 'active',
            'documentation' => 'https://api.xpresspos.id/v1/docs',
            'endpoints' => [
                'health' => '/v1/health',
                'auth' => '/v1/auth/*',
                'products' => '/v1/products/*',
                'orders' => '/v1/orders/*',
            ]
        ]);
    })->name('api.home');
});

// Owner and Admin panels now use path-based routing (/owner and /admin)
// Filament handles all routes for these panels

// Include landing routes for localhost development
// require __DIR__.'/landing.php'; // Commented out to avoid route conflicts

if (app()->environment('local')) {
    // Local development prefix routes (alternative access method)
    Route::prefix('main')->group(function () {
        Route::get('/', function () {
            return view('landing.xpresspos', [
                'title' => 'XpressPOS - AI Maksimalkan Bisnismu'
            ]);
        })->name('main.home');
        
        Route::get('/cart', function () {
            return view('landing.cart');
        })->name('main.cart');
    });

    // API prefix routes for local development
    Route::prefix('api-demo')->group(function () {
        Route::get('/', function () {
            return response()->json([
                'message' => 'XpressPOS API Demo',
                'version' => '1.0',
                'status' => 'active',
                'note' => 'This is local development API demo',
                'endpoints' => [
                    'health' => '/api/v1/health',
                    'auth' => '/api/v1/auth/*',
                    'products' => '/api/v1/products/*',
                    'orders' => '/api/v1/orders/*',
                ]
            ]);
        })->name('api.demo');
    });
}

// Health check endpoint for Docker healthcheck
Route::get('/healthz', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
    ]);
})->name('healthz');
