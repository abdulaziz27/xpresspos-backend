<?php

use Illuminate\Support\Facades\Route;

// Admin routes - hanya untuk admin.xpresspos.id
// Filament admin panel akan handle semua admin routes
// Redirect root ke admin panel
Route::get('/', function () {
    return redirect('/admin');
});

// Custom admin routes jika diperlukan
Route::middleware(['auth', 'role:super-admin'])->group(function () {
    // Additional admin routes can be added here
});
