<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use App\Services\XenditService;

class XenditWebhookSecurity
{
    protected XenditService $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    /**
     * Handle an incoming webhook request with comprehensive security validation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Rate limiting per IP
        if (!$this->checkRateLimit($request)) {
            $this->logSecurityEvent($request, 'rate_limit_exceeded', [
                'ip' => $request->ip(),
                'attempts' => $this->getRateLimitAttempts($request),
            ]);
            
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // 2. IP whitelist validation (if configured)
        if (!$this->validateIpWhitelist($request)) {
            $this->logSecurityEvent($request, 'ip_not_whitelisted', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json(['error' => 'Unauthorized IP'], 403);
        }

        // 3. Content-Type validation
        if (!$this->validateContentType($request)) {
            $this->logSecurityEvent($request, 'invalid_content_type', [
                'content_type' => $request->header('Content-Type'),
            ]);
            
            return response()->json(['error' => 'Invalid content type'], 400);
        }

        // 4. Request size validation
        if (!$this->validateRequestSize($request)) {
            $this->logSecurityEvent($request, 'request_too_large', [
                'content_length' => $request->header('Content-Length'),
            ]);
            
            return response()->json(['error' => 'Request too large'], 413);
        }

        // 5. Enhanced signature validation
        if (!$this->validateEnhancedSignature($request)) {
            $this->logSecurityEvent($request, 'invalid_signature', [
                'signature_header' => $request->header('x-callback-token'),
                'content_hash' => hash('sha256', $request->getContent()),
            ]);
            
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 6. Replay attack protection
        if (!$this->validateReplayProtection($request)) {
            $this->logSecurityEvent($request, 'replay_attack_detected', [
                'timestamp' => $request->header('x-timestamp'),
                'signature' => $request->header('x-callback-token'),
            ]);
            
            return response()->json(['error' => 'Request replay detected'], 400);
        }

        // 7. Payload validation
        if (!$this->validatePayloadStructure($request)) {
            $this->logSecurityEvent($request, 'invalid_payload_structure', [
                'payload_keys' => array_keys($request->all()),
            ]);
            
            return response()->json(['error' => 'Invalid payload structure'], 400);
        }

        // Log successful validation
        $this->logSecurityEvent($request, 'webhook_validated', [
            'ip' => $request->ip(),
            'payload_size' => strlen($request->getContent()),
        ], 'info');

        return $next($request);
    }

    /**
     * Check rate limiting for webhook requests.
     */
    protected function checkRateLimit(Request $request): bool
    {
        $key = 'xendit_webhook:' . $request->ip();
        $maxAttempts = config('xendit.security.rate_limit.max_attempts', 60);
        $decayMinutes = config('xendit.security.rate_limit.decay_minutes', 1);

        return RateLimiter::attempt(
            $key,
            $maxAttempts,
            function () {
                // Allow the request
            },
            $decayMinutes * 60
        );
    }

    /**
     * Get current rate limit attempts for an IP.
     */
    protected function getRateLimitAttempts(Request $request): int
    {
        $key = 'xendit_webhook:' . $request->ip();
        return RateLimiter::attempts($key);
    }

    /**
     * Validate IP whitelist if configured.
     */
    protected function validateIpWhitelist(Request $request): bool
    {
        $whitelist = config('xendit.security.ip_whitelist', []);
        
        // If no whitelist configured, allow all IPs
        if (empty($whitelist)) {
            return true;
        }

        $clientIp = $request->ip();
        
        // Check if IP is in whitelist
        foreach ($whitelist as $allowedIp) {
            if ($this->ipMatches($clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches pattern (supports CIDR notation).
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation support
        if (strpos($pattern, '/') !== false) {
            [$subnet, $mask] = explode('/', $pattern);
            
            if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $maskLong = -1 << (32 - (int)$mask);
                
                return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
            }
        }

        return false;
    }

    /**
     * Validate request content type.
     */
    protected function validateContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');
        $allowedTypes = [
            'application/json',
            'application/json; charset=utf-8',
        ];

        return in_array($contentType, $allowedTypes, true);
    }

    /**
     * Validate request size limits.
     */
    protected function validateRequestSize(Request $request): bool
    {
        $maxSize = config('xendit.security.max_payload_size', 1024 * 1024); // 1MB default
        $contentLength = $request->header('Content-Length');
        
        if ($contentLength && (int)$contentLength > $maxSize) {
            return false;
        }

        // Also check actual content size
        $actualSize = strlen($request->getContent());
        return $actualSize <= $maxSize;
    }

    /**
     * Enhanced signature validation with multiple verification methods.
     */
    protected function validateEnhancedSignature(Request $request): bool
    {
        $signature = $request->header('x-callback-token');
        
        if (!$signature) {
            return false;
        }

        // Method 1: Simple token comparison (current Xendit method)
        $webhookToken = config('xendit.webhook_token');
        if ($webhookToken && hash_equals($webhookToken, $signature)) {
            return true;
        }

        // Method 2: HMAC signature validation (if Xendit supports it in future)
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhookToken);
        
        if (hash_equals($expectedSignature, $signature)) {
            return true;
        }

        // Method 3: Use XenditService validation
        return $this->xenditService->validateWebhook($payload, $signature);
    }

    /**
     * Protect against replay attacks using timestamp validation.
     */
    protected function validateReplayProtection(Request $request): bool
    {
        // Create a unique request identifier
        $requestId = $this->generateRequestId($request);
        
        // Check if we've seen this request before
        $cacheKey = "xendit_webhook_request:{$requestId}";
        
        if (Cache::has($cacheKey)) {
            return false; // Replay detected
        }

        // Store request ID for replay protection (expire after 5 minutes)
        Cache::put($cacheKey, true, 300);
        
        return true;
    }

    /**
     * Generate unique request identifier for replay protection.
     */
    protected function generateRequestId(Request $request): string
    {
        $payload = $request->getContent();
        $signature = $request->header('x-callback-token', '');
        $timestamp = $request->header('x-timestamp', time());
        
        return hash('sha256', $payload . $signature . $timestamp);
    }

    /**
     * Validate webhook payload structure.
     */
    protected function validatePayloadStructure(Request $request): bool
    {
        $payload = $request->all();
        
        // Basic structure validation for Xendit webhooks
        $requiredFields = ['id', 'external_id', 'status'];
        
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return false;
            }
        }

        // Validate field types
        if (!is_string($payload['id']) || empty($payload['id'])) {
            return false;
        }

        if (!is_string($payload['external_id']) || empty($payload['external_id'])) {
            return false;
        }

        if (!is_string($payload['status']) || empty($payload['status'])) {
            return false;
        }

        // Validate status values
        $validStatuses = ['PENDING', 'PAID', 'EXPIRED', 'FAILED'];
        if (!in_array(strtoupper($payload['status']), $validStatuses)) {
            return false;
        }

        return true;
    }

    /**
     * Log security events for monitoring and audit.
     */
    protected function logSecurityEvent(Request $request, string $event, array $context = [], string $level = 'warning'): void
    {
        $logData = [
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString(),
            'context' => $context,
        ];

        Log::log($level, "Xendit webhook security event: {$event}", $logData);

        // Also store in cache for monitoring dashboard
        $this->storeSecurityMetrics($event, $request);
    }

    /**
     * Store security metrics for monitoring dashboard.
     */
    protected function storeSecurityMetrics(string $event, Request $request): void
    {
        $metricsKey = 'xendit_security_metrics:' . now()->format('Y-m-d-H');
        
        $metrics = Cache::get($metricsKey, []);
        $metrics[$event] = ($metrics[$event] ?? 0) + 1;
        $metrics['total_requests'] = ($metrics['total_requests'] ?? 0) + 1;
        
        // Store for 24 hours
        Cache::put($metricsKey, $metrics, 1440);
    }
}