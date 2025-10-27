<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;

// Main domain routing (xpresspos.id)
Route::domain(config('domains.main'))->group(function () {
    Route::get('/', [LandingController::class, 'index'])->name('landing.main');
    
    // Auth routes untuk landing (redirect ke owner dashboard setelah login)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LandingController::class, 'showLogin'])->name('login');
        Route::post('/login', [LandingController::class, 'login'])->name('login.post');
        Route::get('/register', [LandingController::class, 'showRegister'])->name('register');
        Route::post('/register', [LandingController::class, 'register'])->name('register.post');
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

    // Multi-step checkout
    Route::get('/checkout', [LandingController::class, 'showCheckout'])->name('landing.checkout');
    Route::get('/checkout/business-info', [LandingController::class, 'showCheckoutStep2'])->name('landing.checkout.step2');
    Route::post('/checkout/business-info', [LandingController::class, 'processCheckoutStep2'])->name('landing.checkout.step2.process');
    Route::get('/checkout/payment-method', [LandingController::class, 'showCheckoutStep3'])->name('landing.checkout.step3');
    Route::post('/checkout/payment-method', [LandingController::class, 'processCheckoutStep3'])->name('landing.checkout.step3.process');

    // Legacy routes (keep for backward compatibility)
    Route::post('/subscription', [LandingController::class, 'processSubscription'])->name('landing.subscription.process');
    
    // Cart route
    Route::get('/cart', function () {
        return view('landing.cart');
    })->name('cart');
    
    Route::get('/company', function () {
        return view('company', [
            'title' => 'Company - XpressPOS'
        ]);
    })->name('company');
});

// Local development domains
if (app()->environment('local')) {
    Route::domain(config('domains.local.main'))->group(function () {
        Route::get('/', [LandingController::class, 'index'])->name('landing.main.local');
    });
}

// Test route (optional)
Route::get('/test', function () {
    return view('test-simple');
})->name('test.simple');

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

// Owner domain routing (dashboard.xpresspos.id) - Filament handles all routes
Route::domain(config('domains.owner'))->group(function () {
    // Filament panel handles all routes at root domain
    // No additional routes needed here
});

// Admin domain routing (admin.xpresspos.id) - Filament handles all routes
Route::domain(config('domains.admin'))->group(function () {
    // Filament panel handles all routes at root domain
    // No additional routes needed here
});

// Include landing routes for localhost development
// require __DIR__.'/landing.php'; // Commented out to avoid route conflicts

// Localhost fallback routes (for development without domain setup)
Route::get('/', [LandingController::class, 'index'])->name('home');

Route::get('/forgot-password', function () {
    return view('landing.auth.forgot-password');
})->name('forgot-password');

Route::get('/company', function () {
    return view('company', [
        'title' => 'Company - XpressPOS'
    ]);
})->name('company.fallback');

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