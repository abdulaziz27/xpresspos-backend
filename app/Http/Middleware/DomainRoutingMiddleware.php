<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class DomainRoutingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Map domains to route prefixes
        $domainMap = [
            config('domains.landing') => 'landing',
            config('domains.owner') => 'owner',
            config('domains.admin') => 'admin',
            config('domains.api') => 'api',
        ];

        // Check if the host matches any configured domain
        foreach ($domainMap as $domain => $prefix) {
            if ($host === $domain || $host === $domain . ':8000') {
                // Set the route prefix based on domain
                $request->attributes->set('domain_prefix', $prefix);
                break;
            }
        }

        return $next($request);
    }
}
