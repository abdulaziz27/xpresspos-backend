<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;

// Landing page routes - hanya untuk xpresspos.id
Route::get('/', [LandingController::class, 'index'])->name('landing.home');

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
Route::get('/checkout', [LandingController::class, 'showCheckout'])->name('landing.checkout'); // Step 1: Cart
Route::get('/checkout/business-info', [LandingController::class, 'showCheckoutStep2'])->name('landing.checkout.step2'); // Step 2: Business Info
Route::post('/checkout/business-info', [LandingController::class, 'processCheckoutStep2'])->name('landing.checkout.step2.process');
Route::get('/checkout/payment-method', [LandingController::class, 'showCheckoutStep3'])->name('landing.checkout.step3'); // Step 3: Payment
Route::post('/checkout/payment-method', [LandingController::class, 'processCheckoutStep3'])->name('landing.checkout.step3.process');

// Legacy routes (keep for backward compatibility)
Route::post('/subscription', [LandingController::class, 'processSubscription'])->name('landing.subscription.process');
Route::get('/payment', [LandingController::class, 'showPayment'])->name('landing.payment');
Route::post('/payment/process', [LandingController::class, 'processPayment'])->name('landing.payment.process');

// Payment result pages
Route::get('/payment/success', [LandingController::class, 'paymentSuccess'])->middleware('dev.payment')->name('landing.payment.success');
Route::get('/payment/failed', [LandingController::class, 'paymentFailed'])->middleware('dev.payment')->name('landing.payment.failed');
Route::get('/customer-dashboard', [LandingController::class, 'customerDashboard'])->name('landing.customer.dashboard');
Route::get('/customer-login', function() {
    return view('landing.customer-login');
})->name('landing.customer.login');