<?php

use Illuminate\Support\Facades\Route;

// Main domain routing (xpresspos.id)
Route::domain(config('domains.main'))->group(function () {
    Route::get('/', function () {
        return view('landing.xpresspos-invezgo', [
            'title' => 'XpressPOS - AI Maksimalkan Bisnismu'
        ]);
    })->name('landing.main');
    
    Route::get('/company', function () {
        return view('company', [
            'title' => 'Company - XpressPOS'
        ]);
    })->name('company');
});

// Local development domains
if (app()->environment('local')) {
    Route::domain(config('domains.local.main'))->group(function () {
        Route::get('/', function () {
            return view('landing.xpresspos-invezgo', [
                'title' => 'XpressPOS - AI Maksimalkan Bisnismu'
            ]);
        })->name('landing.main.local');
    });
}

// Test route (optional)
Route::get('/test', function () {
    return view('test-simple');
})->name('test.simple');

// Test route for debugging
Route::get('/test-navbar', function () {
    return view('landing.xpresspos-invezgo', [
        'title' => 'XpressPOS - AI Maksimalkan Bisnismu'
    ]);
});

// Default fallback route for other domains (including localhost)
Route::get('/', function () {
    // Force show XpressPOS landing page for all localhost requests
    return view('landing.xpresspos-invezgo', [
        'title' => 'XpressPOS - AI Maksimalkan Bisnismu'
    ]);
});