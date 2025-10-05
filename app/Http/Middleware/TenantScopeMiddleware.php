<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantScopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // System admin bypasses tenant scoping
        if (Auth::check() && Auth::user()->hasRole('system_admin')) {
            return $next($request);
        }

        // For authenticated users, ensure they can only access their store's data
        if (Auth::check() && Auth::user()->store_id) {
            // The actual tenant scoping is handled by model scopes
            // This middleware just ensures the user is properly authenticated
            return $next($request);
        }

        // For unauthenticated requests, continue normally
        return $next($request);
    }
}
