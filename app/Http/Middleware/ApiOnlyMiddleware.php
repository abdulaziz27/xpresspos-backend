<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiOnlyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        
        // Check if this is API domain
        if (str_contains($host, 'api.')) {
            // Force JSON response for all requests on API domain
            $request->headers->set('Accept', 'application/json');
            $request->headers->set('Content-Type', 'application/json');
            
            // If not an API route, return API info
            if (!$request->is('api/*') && !$request->is('/') && !$request->is('docs')) {
                return response()->json([
                    'message' => 'XpressPOS API',
                    'version' => '1.0',
                    'status' => 'active',
                    'error' => 'Endpoint not found',
                    'documentation' => env('API_URL') . '/docs'
                ], 404);
            }
        }

        return $next($request);
    }
}