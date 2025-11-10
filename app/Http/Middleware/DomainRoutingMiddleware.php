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

        // Skip domain routing for local development
        if ($this->isLocalEnvironment($request)) {
            // Set default prefix for local development
            $request->attributes->set('domain_prefix', 'landing');
            return $next($request);
        }

        // Map domains to route prefixes using env config
        $domainMap = array_filter([
            env('LANDING_DOMAIN') => 'landing',
            env('API_DOMAIN') => 'api',
        ]);

        // Check if the host matches any configured domain
        foreach ($domainMap as $domain => $prefix) {
            if ($host === $domain || $host === $domain . ':8000') {
                // Set the route prefix based on domain
                $request->attributes->set('domain_prefix', $prefix);
                
                // For API domain, ensure only API responses
                if ($prefix === 'api' && !$request->is('api/*')) {
                    return response()->json([
                        'message' => 'XpressPOS API',
                        'version' => '1.0',
                        'status' => 'active',
                        'documentation' => env('API_URL') . '/docs'
                    ]);
                }
                
                break;
            }
        }

        return $next($request);
    }

    /**
     * Check if the request is from local development environment
     */
    private function isLocalEnvironment(Request $request): bool
    {
        $host = $request->getHost();
        
        // Check for local hosts
        $localHosts = ['127.0.0.1', 'localhost'];
        
        return in_array($host, $localHosts) || 
               app()->environment('local') ||
               str_contains($host, '.test') ||
               str_contains($host, '.local');
    }
}
