<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DevelopmentPaymentMiddleware
{
    /**
     * Handle an incoming request for development payment simulation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply in development environment
        if (!app()->environment('local', 'development')) {
            return $next($request);
        }

        // Check if this is a dummy payment redirect
        if ($request->has('dummy') && $request->get('dummy') === 'true') {
            Log::info('Development payment simulation detected', [
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip()
            ]);

            // Add development notice to session
            session()->flash('dev_notice', 'This is a development simulation. No real payment was processed.');
        }

        return $next($request);
    }
}