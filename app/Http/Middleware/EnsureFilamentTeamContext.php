<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentTeamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if ($user && $user->store_id) {
            // Always set team context for Filament routes
            setPermissionsTeamId($user->store_id);
            
            // Debug logging
            \Log::info('EnsureFilamentTeamContext', [
                'user_id' => $user->id,
                'store_id' => $user->store_id,
                'url' => $request->fullUrl(),
                'roles_count' => $user->roles()->count(),
                'has_owner_role' => $user->hasRole('owner'),
            ]);
        }

        return $next($request);
    }
}