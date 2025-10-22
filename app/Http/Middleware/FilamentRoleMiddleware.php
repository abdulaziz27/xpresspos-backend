<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilamentRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Skip role check for login pages
        if ($request->routeIs('filament.*.auth.login') || $request->routeIs('filament.*.auth.*')) {
            return $next($request);
        }

        if (!auth()->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $user = auth()->user();
        
        // For owner panel, check if user has owner role in any store or has store assignment as owner
        if ($role === 'owner') {
            // Set team context first
            if ($user->store_id) {
                setPermissionsTeamId($user->store_id);
            }
            
            $hasOwnerRole = $user->roles()->where('name', 'owner')->exists();
            $hasOwnerAssignment = $user->storeAssignments()
                ->where('assignment_role', 'owner')
                ->exists();
            
            // Debug logging
            \Log::info('FilamentRoleMiddleware owner check', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'store_id' => $user->store_id,
                'has_owner_role' => $hasOwnerRole,
                'has_owner_assignment' => $hasOwnerAssignment,
                'url' => $request->fullUrl(),
            ]);
                
            if (!$hasOwnerRole && !$hasOwnerAssignment) {
                abort(403, 'Unauthorized access to this panel.');
            }
        } else {
            // For other roles, use standard check
            if (!$user->hasRole($role)) {
                abort(403, 'Unauthorized access to this panel.');
            }
        }

        return $next($request);
    }
}
