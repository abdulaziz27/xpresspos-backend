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

        if (!auth()->user()->hasRole($role)) {
            abort(403, 'Unauthorized access to this panel.');
        }

        return $next($request);
    }
}
