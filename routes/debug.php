<?php

use Illuminate\Support\Facades\Route;

Route::get('/debug/user', function () {
    $user = auth()->user();
    
    if (!$user) {
        return response()->json(['error' => 'Not authenticated']);
    }
    
    // Set team context
    if ($user->store_id) {
        setPermissionsTeamId($user->store_id);
    }
    
    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'store_id' => $user->store_id,
        ],
        'roles' => $user->roles->pluck('name'),
        'permissions' => $user->getAllPermissions()->pluck('name')->take(10),
        'has_owner_role' => $user->hasRole('owner'),
        'can_view_products' => $user->can('viewAny', App\Models\Product::class),
        'resource_auth' => [
            'products' => App\Filament\Owner\Resources\Products\ProductResource::canViewAny(),
            'categories' => App\Filament\Owner\Resources\Categories\CategoryResource::canViewAny(),
            'orders' => App\Filament\Owner\Resources\Orders\OrderResource::canViewAny(),
        ]
    ]);
})->middleware(['web', 'auth']);

// Debug route with Filament middleware stack
Route::get('/debug/filament', function () {
    $user = auth()->user();
    
    if (!$user) {
        return response()->json(['error' => 'Not authenticated']);
    }
    
    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'store_id' => $user->store_id,
        ],
        'roles' => $user->roles->pluck('name'),
        'has_owner_role' => $user->hasRole('owner'),
        'current_panel' => Filament\Facades\Filament::getCurrentPanel()?->getId(),
        'resource_auth' => [
            'products' => App\Filament\Owner\Resources\Products\ProductResource::canViewAny(),
            'categories' => App\Filament\Owner\Resources\Categories\CategoryResource::canViewAny(),
            'orders' => App\Filament\Owner\Resources\Orders\OrderResource::canViewAny(),
        ]
    ]);
})->middleware([
    'web',
    'auth',
    App\Http\Middleware\EnsureStoreContext::class,
    App\Http\Middleware\EnsureFilamentTeamContext::class,
]);