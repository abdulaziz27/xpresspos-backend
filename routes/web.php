<?php

use Illuminate\Support\Facades\Route;

// Main domain routing (xpresspos.id)
Route::domain(config('domains.main'))->group(function () {
    Route::get('/', function () {
        return view('landing.xpresspos', [
            'title' => 'XpressPOS - POS Maksimalkan Bisnismu'
        ]);
    })->name('landing.main');
    
    // Auth routes
    Route::get('/login', function () {
        return view('landing.auth.login');
    })->name('auth.login');
    
    Route::get('/register', function () {
        return view('landing.auth.register');
    })->name('auth.register');
    
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
        Route::get('/', function () {
            return view('landing.xpresspos', [
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

// Owner domain routing (owner.xpresspos.id) - redirect to Filament
Route::domain(config('domains.owner'))->group(function () {
    Route::get('/', function () {
        return redirect('/owner-panel');
    })->name('owner.home');
});

// Admin domain routing (admin.xpresspos.id) - redirect to Filament  
Route::domain(config('domains.admin'))->group(function () {
    Route::get('/', function () {
        return redirect('/admin-panel');
    })->name('admin.home');
});

// Localhost fallback routes (for development without domain setup)
Route::get('/', function () {
    return view('landing.xpresspos', [
        'title' => 'XpressPOS - AI Maksimalkan Bisnismu'
    ]);
})->name('home');

Route::get('/login', function () {
    return view('landing.auth.login');
})->name('login');

Route::get('/register', function () {
    return view('landing.auth.register');
})->name('register');

Route::get('/cart', function () {
    return view('landing.cart');
})->name('cart');

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
    
    Route::get('/login', function () {
        return view('landing.auth.login');
    })->name('main.login');
    
    Route::get('/register', function () {
        return view('landing.auth.register');
    })->name('main.register');
    
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