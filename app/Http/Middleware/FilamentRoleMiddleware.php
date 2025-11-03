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
            // CRITICAL: Set team context first before any role/permission checks
            // This ensures Spatie Permission queries use the correct team context
            $storeId = $user->store_id;
            
            // Try to get store_id from primary store assignment if user doesn't have direct store_id
            if (!$storeId) {
                $primaryStore = $user->primaryStore();
                $storeId = $primaryStore?->id;
            }
            
            // Set team context if we have a store_id
            if ($storeId) {
                setPermissionsTeamId($storeId);
            }
            
            // Use hasRole() method which properly respects team context
            // This is more reliable than direct query because Spatie Permission
            // automatically filters by team context when using hasRole()
            $hasOwnerRole = $storeId ? $user->hasRole('owner') : false;
            
            // Also check store assignments as fallback
            // Use enum value to ensure proper matching (database stores as string 'owner')
            $hasOwnerAssignment = $user->storeAssignments()
                ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
                ->exists();
            
            // Debug logging for production troubleshooting
            \Log::info('FilamentRoleMiddleware owner check', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'store_id' => $storeId,
                'user_store_id' => $user->store_id,
                'has_owner_role' => $hasOwnerRole,
                'has_owner_assignment' => $hasOwnerAssignment,
                'current_team_id' => getPermissionsTeamId(),
                'all_roles_count' => $user->roles()->count(),
                'url' => $request->fullUrl(),
                'host' => $request->getHost(),
            ]);
                
            if (!$hasOwnerRole && !$hasOwnerAssignment) {
                \Log::warning('FilamentRoleMiddleware: Access denied', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'store_id' => $storeId,
                    'url' => $request->fullUrl(),
                ]);
                abort(403, 'Unauthorized access to this panel.');
            }
        } else {
            // For admin_sistem role, check for admin_sistem or super-admin
            if ($role === 'admin_sistem') {
                $hasAdminRole = $user->hasRole(['admin_sistem', 'super-admin']);
                if (!$hasAdminRole) {
                    abort(403, 'Unauthorized access to this panel.');
                }
            } else {
                // For other roles, use standard check
                if (!$user->hasRole($role)) {
                    abort(403, 'Unauthorized access to this panel.');
                }
            }
        }

        return $next($request);
    }
}
