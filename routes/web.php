<?php

use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('landing');

Route::view('/login', 'auth.login')->name('login');
Route::view('/company', 'company')->name('company');

Route::prefix('owner')->as('owner.')->group(function (): void {
    Route::get('dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');
});

Route::view('/owner', 'owner.dashboard');
