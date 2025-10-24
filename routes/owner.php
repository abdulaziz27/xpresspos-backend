<?php

// Owner routes - redirect to Filament panel
Route::get('/', function () {
    if (app()->environment('production') && env('OWNER_DOMAIN')) {
        return redirect('/');  // Filament panel di root domain
    } else {
        return redirect('/owner-panel');  // Filament panel di path
    }
})->name('owner.dashboard');

// Logout route
Route::post('/logout', function () {
    auth()->logout();
    if (app()->environment('production') && env('FRONTEND_URL')) {
        return redirect()->to(env('FRONTEND_URL'));
    } else {
        return redirect('/landing');
    }
})->name('owner.logout');
