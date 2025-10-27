<?php

// Owner routes - Filament panel handles all routes at root domain
// This file is kept for any additional owner-specific routes if needed

// Logout route for custom logout handling
Route::post('/logout', function () {
    auth()->logout();
    if (app()->environment('production') && env('FRONTEND_URL')) {
        return redirect()->to(env('FRONTEND_URL'));
    } else {
        return redirect('/');
    }
})->name('owner.logout');
