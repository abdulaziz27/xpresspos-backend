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