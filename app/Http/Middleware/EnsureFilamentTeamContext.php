<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentTeamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('EnsureFilamentTeamContext: Entry', [
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'is_authenticated' => auth()->check(),
            'user_id' => auth()->id(),
            'method' => $request->method(),
            'host' => $request->getHost(),
        ]);
        
        $user = auth()->user();
        
        if ($user) {
            $storeId = $user->store_id;
            
            // If user doesn't have direct store_id, try to get from primary store assignment
            if (!$storeId) {
                $primaryStore = $user->primaryStore();
                $storeId = $primaryStore?->id;
                
                // Update user's store_id attribute for consistency
                if ($storeId) {
                    $user->setAttribute('store_id', $storeId);
                }
            }
            
            if ($storeId) {
                // Always set team context for Filament routes
                setPermissionsTeamId($storeId);
                
                // Debug logging for production troubleshooting
                \Log::info('EnsureFilamentTeamContext', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'store_id' => $storeId,
                    'user_store_id' => $user->getOriginal('store_id'),
                    'url' => $request->fullUrl(),
                    'host' => $request->getHost(),
                    'current_team_id' => getPermissionsTeamId(),
                ]);
            } else {
                \Log::warning('EnsureFilamentTeamContext: User without store_id', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'url' => $request->fullUrl(),
                ]);
            }
        } else {
            \Log::warning('EnsureFilamentTeamContext: No authenticated user', [
                'url' => $request->fullUrl(),
            ]);
        }

        return $next($request);
    }
}