<?php

use Illuminate\Support\Facades\Route;

// Default fallback route
Route::get('/', function () {
    $host = request()->getHost();
    
    // Production domain routing
    if (app()->environment('production') && env('LANDING_DOMAIN')) {
        if (str_contains($host, 'api.')) {
            return response()->json([
                'message' => 'XpressPOS API',
                'version' => '1.0',
                'status' => 'active',
                'documentation' => env('API_URL') . '/docs'
            ]);
        }
        
        if (str_contains($host, 'user.')) {
            return redirect('/');  // Filament owner panel di root
        }
        
        if (str_contains($host, 'admin.')) {
            return redirect('/');  // Filament admin panel di root
        }
        
        // Default ke landing page
        return view('landing.home');
    }
    
    // Local development - show navigation page
    return view('local-nav');
});