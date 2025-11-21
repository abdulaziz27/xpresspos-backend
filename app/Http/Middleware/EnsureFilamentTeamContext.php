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
            $tenantId = $user->currentTenantId();
            
            if ($tenantId) {
                // Always set team context for Filament routes using tenant_id
                setPermissionsTeamId($tenantId);
                
                // Debug logging for production troubleshooting
                \Log::info('EnsureFilamentTeamContext', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'tenant_id' => $tenantId,
                    'url' => $request->fullUrl(),
                    'host' => $request->getHost(),
                    'current_team_id' => getPermissionsTeamId(),
                ]);
            } else {
                \Log::warning('EnsureFilamentTeamContext: User without tenant_id', [
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