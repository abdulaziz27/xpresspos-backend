<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as Middleware;
use Illuminate\Http\Request;

class EnsureEmailIsVerified extends Middleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null)
    {
        return parent::handle($request, $next, $redirectToRoute);
    }
}
