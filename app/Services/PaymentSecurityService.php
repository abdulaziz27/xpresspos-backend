<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class PaymentSecurityService
{
    /**
     * Log payment security events with comprehensive context.
     */
    public function logSecurityEvent(
        string $event,
        string $level = 'warning',
        array $context = [],
        ?Request $request = null,
        ?User $user = null
    ): void {
        $request = $request ?? request();
        $user = $user ?? auth()->user();

        $logData = [
            'event' => $event,
            'level' => $level,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'timestamp' => now()->toISOString(),
            'context' => $context,
            'headers' => $this->sanitizeHeaders($request->headers->all()),
        ];

        // Log to Laravel log
        Log::log($level, "Payment security event: {$event}", $logData);

        // Store in database for audit trail
        $this->storeAuditLog($event, $logData);

        // Update security metrics
        $this->updateSecurityMetrics($event, $request);

        // Check for security alerts
        $this->checkSecurityAlerts($event, $request);
    }

    /**
     * Log webhook security events specifically.
     */
    public function logWebhookSecurityEvent(
        string $event,
        Request $request,
        array $context = [],
        string $level = 'warning'
    ): void {
        $webhookContext = array_merge($context, [
            'webhook_type' => 'xendit',
            'payload_size' => strlen($request->getContent()),
            'content_type' => $request->header('Content-Type'),
            'signature_header' => $request->header('x-callback-token') ? 'present' : 'missing',
        ]);

        $this->logSecurityEvent($event, $level, $webhookContext, $request);
    }

    /**
     * Log payment operation security events.
     */
    public function logPaymentOperationEvent(
        string $operation,
        array $paymentData,
        ?User $user = null,
        string $level = 'info'
    ): void {
        $sanitizedData = $this->sanitizePaymentData($paymentData);
        
        $context = [
            'operation' => $operation,
            'payment_data' => $sanitizedData,
            'store_id' => $paymentData['store_id'] ?? null,
            'subscription_id' => $paymentData['subscription_id'] ?? null,
            'amount' => $paymentData['amount'] ?? null,
        ];

        $this->logSecurityEvent("payment_operation_{$operation}", $level, $context, null, $user);
    }

    /**
     * Store audit log in database for compliance.
     */
    protected function storeAuditLog(string $event, array $logData): void
    {
        try {
            DB::table('payment_security_logs')->insert([
                'event' => $event,
                'level' => $logData['level'],
                'ip_address' => $logData['ip_address'],
                'user_agent' => $logData['user_agent'],
                'url' => $logData['url'],
                'method' => $logData['method'],
                'user_id' => $logData['user_id'],
                'user_email' => $logData['user_email'],
                'context' => json_encode($logData['context']),
                'headers' => json_encode($logData['headers']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't let audit logging failures break the main flow
            Log::error('Failed to store payment security audit log', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);
        }
    }

    /**
     * Update security metrics for monitoring.
     */
    protected function updateSecurityMetrics(string $event, Request $request): void
    {
        $hour = now()->format('Y-m-d-H');
        $day = now()->format('Y-m-d');
        
        // Hourly metrics
        $hourlyKey = "payment_security_metrics:hourly:{$hour}";
        $hourlyMetrics = Cache::get($hourlyKey, []);
        $hourlyMetrics[$event] = ($hourlyMetrics[$event] ?? 0) + 1;
        $hourlyMetrics['total_events'] = ($hourlyMetrics['total_events'] ?? 0) + 1;
        Cache::put($hourlyKey, $hourlyMetrics, 3600); // 1 hour

        // Daily metrics
        $dailyKey = "payment_security_metrics:daily:{$day}";
        $dailyMetrics = Cache::get($dailyKey, []);
        $dailyMetrics[$event] = ($dailyMetrics[$event] ?? 0) + 1;
        $dailyMetrics['total_events'] = ($dailyMetrics['total_events'] ?? 0) + 1;
        Cache::put($dailyKey, $dailyMetrics, 86400); // 24 hours

        // IP-based metrics
        $ipKey = "payment_security_metrics:ip:{$request->ip()}:{$hour}";
        $ipMetrics = Cache::get($ipKey, []);
        $ipMetrics[$event] = ($ipMetrics[$event] ?? 0) + 1;
        Cache::put($ipKey, $ipMetrics, 3600);
    }

    /**
     * Check for security alerts and trigger notifications.
     */
    protected function checkSecurityAlerts(string $event, Request $request): void
    {
        $alertThresholds = config('xendit.security.alert_thresholds', []);
        
        if (!isset($alertThresholds[$event])) {
            return;
        }

        $threshold = $alertThresholds[$event];
        $timeWindow = $threshold['time_window'] ?? 3600; // 1 hour default
        $maxEvents = $threshold['max_events'] ?? 10;

        // Check if threshold exceeded
        $windowStart = now()->subSeconds($timeWindow);
        $recentEvents = $this->getRecentSecurityEvents($event, $request->ip(), $windowStart);

        if ($recentEvents >= $maxEvents) {
            $this->triggerSecurityAlert($event, $request, $recentEvents, $threshold);
        }
    }

    /**
     * Get count of recent security events for an IP.
     */
    protected function getRecentSecurityEvents(string $event, string $ip, Carbon $since): int
    {
        try {
            return DB::table('payment_security_logs')
                ->where('event', $event)
                ->where('ip_address', $ip)
                ->where('created_at', '>=', $since)
                ->count();
        } catch (\Exception $e) {
            Log::error('Failed to query recent security events', [
                'error' => $e->getMessage(),
                'event' => $event,
                'ip' => $ip,
            ]);
            return 0;
        }
    }

    /**
     * Trigger security alert notification.
     */
    protected function triggerSecurityAlert(string $event, Request $request, int $eventCount, array $threshold): void
    {
        $alertData = [
            'event' => $event,
            'ip_address' => $request->ip(),
            'event_count' => $eventCount,
            'threshold' => $threshold,
            'time_window' => $threshold['time_window'] ?? 3600,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ];

        // Log critical alert
        Log::critical('Payment security alert triggered', $alertData);

        // Store alert in cache for dashboard
        $alertKey = 'payment_security_alerts:' . now()->format('Y-m-d-H-i');
        Cache::put($alertKey, $alertData, 86400); // 24 hours

        // TODO: Send notification to administrators
        // This could be email, Slack, SMS, etc.
        $this->notifyAdministrators($alertData);
    }

    /**
     * Notify administrators of security alerts.
     */
    protected function notifyAdministrators(array $alertData): void
    {
        // Implementation depends on notification preferences
        // Could use Laravel notifications, email, Slack webhook, etc.
        
        try {
            // Example: Log for now, implement actual notifications as needed
            Log::info('Security alert notification would be sent', $alertData);
            
            // TODO: Implement actual notification logic
            // Notification::route('mail', config('xendit.security.admin_email'))
            //     ->notify(new PaymentSecurityAlert($alertData));
            
        } catch (\Exception $e) {
            Log::error('Failed to send security alert notification', [
                'error' => $e->getMessage(),
                'alert_data' => $alertData,
            ]);
        }
    }

    /**
     * Sanitize headers for logging (remove sensitive data).
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-callback-token',
            'cookie',
        ];

        $sanitized = [];
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_array($value) ? $value : [$value];
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize payment data for logging (remove sensitive information).
     */
    protected function sanitizePaymentData(array $paymentData): array
    {
        $sensitiveFields = [
            'card_number',
            'cvv',
            'pin',
            'password',
            'api_key',
            'secret',
            'token',
        ];

        $sanitized = [];
        foreach ($paymentData as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizePaymentData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get security metrics for dashboard.
     */
    public function getSecurityMetrics(string $period = 'hourly', int $hours = 24): array
    {
        $metrics = [];
        $now = now();

        for ($i = 0; $i < $hours; $i++) {
            $time = $now->copy()->subHours($i);
            $key = $period === 'hourly' 
                ? "payment_security_metrics:hourly:{$time->format('Y-m-d-H')}"
                : "payment_security_metrics:daily:{$time->format('Y-m-d')}";
            
            $periodMetrics = Cache::get($key, []);
            $metrics[$time->format($period === 'hourly' ? 'H:00' : 'Y-m-d')] = $periodMetrics;
        }

        return array_reverse($metrics, true);
    }

    /**
     * Get recent security alerts.
     */
    public function getRecentAlerts(int $hours = 24): array
    {
        $alerts = [];
        $now = now();

        for ($i = 0; $i < $hours * 60; $i++) { // Check every minute
            $time = $now->copy()->subMinutes($i);
            $key = 'payment_security_alerts:' . $time->format('Y-m-d-H-i');
            
            $alert = Cache::get($key);
            if ($alert) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Check if IP is currently blocked.
     */
    public function isIpBlocked(string $ip): bool
    {
        $blockKey = "payment_security_blocked_ip:{$ip}";
        return Cache::has($blockKey);
    }

    /**
     * Block IP address temporarily.
     */
    public function blockIp(string $ip, int $minutes = 60, string $reason = 'Security violation'): void
    {
        $blockKey = "payment_security_blocked_ip:{$ip}";
        $blockData = [
            'ip' => $ip,
            'reason' => $reason,
            'blocked_at' => now()->toISOString(),
            'expires_at' => now()->addMinutes($minutes)->toISOString(),
        ];

        Cache::put($blockKey, $blockData, $minutes * 60);

        $this->logSecurityEvent('ip_blocked', 'critical', [
            'ip' => $ip,
            'reason' => $reason,
            'duration_minutes' => $minutes,
        ]);
    }

    /**
     * Unblock IP address.
     */
    public function unblockIp(string $ip): void
    {
        $blockKey = "payment_security_blocked_ip:{$ip}";
        Cache::forget($blockKey);

        $this->logSecurityEvent('ip_unblocked', 'info', [
            'ip' => $ip,
        ]);
    }
}