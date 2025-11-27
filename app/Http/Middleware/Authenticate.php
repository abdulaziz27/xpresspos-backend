<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $guard = $guards[0] ?? null;

        \Log::info('Authenticate middleware: Entry', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'guard' => $guard,
            'is_guest' => $this->auth->guard($guard)->guest(),
            'is_authenticated' => $this->auth->guard($guard)->check(),
            'user_id' => $this->auth->guard($guard)->id(),
        ]);

        if ($this->auth->guard($guard)->guest()) {
            \Log::warning('Authenticate middleware: User is guest, redirecting', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'guard' => $guard,
                'redirect_to' => $this->redirectTo($request),
            ]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Authentication required to access this resource',
                        'details' => []
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1',
                        'request_id' => uniqid()
                    ]
                ], 401);
            }

            return redirect()->guest($this->redirectTo($request));
        }

        \Log::info('Authenticate middleware: User authenticated, proceeding', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'guard' => $guard,
            'user_id' => $this->auth->guard($guard)->id(),
        ]);

        // Set the authenticated user for the request
        $request->setUserResolver(function () use ($guard) {
            return $this->auth->guard($guard)->user();
        });

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('landing.login');
    }
}
