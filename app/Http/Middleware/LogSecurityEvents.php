<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSecurityEvents
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log unauthorized access attempts
        if ($response->getStatusCode() === 403) {
            $this->logUnauthorizedAccess($request);
        }

        return $response;
    }

    protected function logUnauthorizedAccess(Request $request): void
    {
        $user = auth()->user();
        
        Log::warning('Unauthorized access attempt', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'timestamp' => now(),
        ]);

        // Also log to database for audit purposes
        if ($user) {
            \App\Models\PermissionAuditLog::create([
                'store_id' => app(\App\Services\StoreContext::class)->current($user),
                'user_id' => $user->id,
                'changed_by' => $user->id,
                'action' => 'unauthorized_access_attempt',
                'permission' => null,
                'old_value' => $request->fullUrl(),
                'new_value' => 'DENIED',
                'notes' => 'IP: ' . $request->ip() . ', User Agent: ' . $request->userAgent(),
            ]);
        }
    }
}