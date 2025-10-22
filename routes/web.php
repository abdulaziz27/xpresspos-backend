<?php

use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

// Root route
Route::get('/', LandingPageController::class)->name('landing');

Route::view('/login', 'auth.login')->name('login');
Route::view('/company', 'company')->name('company');

Route::get('/healthz', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'timestamp' => now()->toISOString(),
    ]);
})->name('healthz');

require __DIR__.'/debug.php';
