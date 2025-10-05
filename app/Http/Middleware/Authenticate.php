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

        if ($this->auth->guard($guard)->guest()) {
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
        return $request->expectsJson() ? null : route('login');
    }
}
