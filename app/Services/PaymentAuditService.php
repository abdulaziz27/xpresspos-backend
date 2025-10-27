<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SubscriptionPayment;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentAuditService
{
    /**
     * Log payment operation for audit trail.
     */
    public function logPaymentOperation(
        string $operation,
        string $entityType,
        string|int $entityId,
        array $oldData = [],
        array $newData = [],
        ?User $user = null,
        ?Request $request = null
    ): void {
        try {
            $request = $request ?? request();
            $user = $user ?? auth()->user();

            $auditData = [
                'operation' => $operation,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $this->sanitizeAuditData($oldData),
                'new_data' => $this->sanitizeAuditData($newData),
                'changes' => $this->calculateChanges($oldData, $newData),
                'request_id' => $request->header('X-Request-ID') ?? uniqid(),
                'session_id' => session()->getId(),
                'created_at' => now(),
            ];

            DB::table('payment_audit_logs')->insert($auditData);

            // Also log to Laravel log for immediate visibility
            $changesArray = json_decode($auditData['changes'], true) ?? [];
            Log::info('Payment operation audit', [
                'operation' => $operation,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $user?->id,
                'changes_count' => count($changesArray),
            ]);

        } catch (\Exception $e) {
            // Don't let audit logging failures break the main flow
            Log::error('Failed to log payment audit', [
                'operation' => $operation,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log subscription payment creation.
     */
    public function logSubscriptionPaymentCreated(SubscriptionPayment $payment, ?User $user = null): void
    {
        $this->logPaymentOperation(
            'created',
            'subscription_payment',
            $payment->id,
            [],
            $payment->toArray(),
            $user
        );
    }

    /**
     * Log subscription payment status change.
     */
    public function logSubscriptionPaymentStatusChange(
        SubscriptionPayment $payment,
        string $oldStatus,
        array $webhookData = [],
        ?User $user = null
    ): void {
        $this->logPaymentOperation(
            'status_changed',
            'subscription_payment',
            $payment->id,
            ['status' => $oldStatus],
            [
                'status' => $payment->status,
                'webhook_data' => $this->sanitizeWebhookData($webhookData),
            ],
            $user
        );
    }

    /**
     * Log store payment creation.
     */
    public function logStorePaymentCreated(Payment $payment, ?User $user = null): void
    {
        $this->logPaymentOperation(
            'created',
            'store_payment',
            $payment->id,
            [],
            $payment->toArray(),
            $user
        );
    }

    /**
     * Log API key operations.
     */
    public function logApiKeyOperation(
        string $operation,
        int $keyId,
        array $context = [],
        ?User $user = null
    ): void {
        $this->logPaymentOperation(
            $operation,
            'api_key',
            $keyId,
            [],
            $context,
            $user
        );
    }

    /**
     * Log webhook processing.
     */
    public function logWebhookProcessing(
        string $webhookType,
        array $payload,
        string $status,
        ?string $errorMessage = null,
        ?Request $request = null
    ): void {
        try {
            $request = $request ?? request();

            $auditData = [
                'operation' => 'webhook_processed',
                'entity_type' => 'webhook',
                'entity_id' => 0, // No specific entity ID for webhooks
                'user_id' => null, // Webhooks don't have users
                'user_email' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode([]),
                'new_data' => json_encode([
                    'webhook_type' => $webhookType,
                    'payload' => $this->sanitizeWebhookData($payload),
                    'status' => $status,
                    'error_message' => $errorMessage,
                ]),
                'changes' => json_encode([]),
                'request_id' => $request->header('X-Request-ID') ?? uniqid(),
                'session_id' => null,
                'created_at' => now(),
            ];

            DB::table('payment_audit_logs')->insert($auditData);

        } catch (\Exception $e) {
            Log::error('Failed to log webhook audit', [
                'webhook_type' => $webhookType,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get audit trail for a specific entity.
     */
    public function getAuditTrail(
        string $entityType,
        int $entityId,
        int $limit = 50,
        int $offset = 0
    ): array {
        try {
            $logs = DB::table('payment_audit_logs')
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return [
                'success' => true,
                'logs' => $logs->toArray(),
                'total' => $this->getAuditTrailCount($entityType, $entityId),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get audit trail', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => [],
                'total' => 0,
            ];
        }
    }

    /**
     * Get audit trail count for pagination.
     */
    public function getAuditTrailCount(string $entityType, int $entityId): int
    {
        try {
            return DB::table('payment_audit_logs')
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Search audit logs by criteria.
     */
    public function searchAuditLogs(array $criteria, int $limit = 100): array
    {
        try {
            $query = DB::table('payment_audit_logs');

            if (isset($criteria['user_id'])) {
                $query->where('user_id', $criteria['user_id']);
            }

            if (isset($criteria['operation'])) {
                $query->where('operation', $criteria['operation']);
            }

            if (isset($criteria['entity_type'])) {
                $query->where('entity_type', $criteria['entity_type']);
            }

            if (isset($criteria['ip_address'])) {
                $query->where('ip_address', $criteria['ip_address']);
            }

            if (isset($criteria['date_from'])) {
                $query->where('created_at', '>=', $criteria['date_from']);
            }

            if (isset($criteria['date_to'])) {
                $query->where('created_at', '<=', $criteria['date_to']);
            }

            $logs = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return [
                'success' => true,
                'logs' => $logs->toArray(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to search audit logs', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => [],
            ];
        }
    }

    /**
     * Get audit statistics for monitoring.
     */
    public function getAuditStatistics(int $days = 30): array
    {
        try {
            $since = now()->subDays($days);

            $stats = DB::table('payment_audit_logs')
                ->selectRaw('
                    operation,
                    entity_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    DATE(created_at) as date
                ')
                ->where('created_at', '>=', $since)
                ->groupBy(['operation', 'entity_type', 'date'])
                ->orderBy('date', 'desc')
                ->get();

            $summary = DB::table('payment_audit_logs')
                ->selectRaw('
                    COUNT(*) as total_operations,
                    COUNT(DISTINCT user_id) as total_unique_users,
                    COUNT(DISTINCT ip_address) as total_unique_ips,
                    COUNT(DISTINCT entity_type) as total_entity_types
                ')
                ->where('created_at', '>=', $since)
                ->first();

            return [
                'success' => true,
                'period_days' => $days,
                'summary' => $summary,
                'daily_stats' => $stats->toArray(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get audit statistics', [
                'days' => $days,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => null,
                'daily_stats' => [],
            ];
        }
    }

    /**
     * Clean up old audit logs based on retention policy.
     */
    public function cleanupOldAuditLogs(int $retentionDays = 365): array
    {
        try {
            $cutoffDate = now()->subDays($retentionDays);

            $deletedCount = DB::table('payment_audit_logs')
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            Log::info('Audit logs cleanup completed', [
                'retention_days' => $retentionDays,
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toISOString(),
            ]);

            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to cleanup audit logs', [
                'retention_days' => $retentionDays,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'deleted_count' => 0,
            ];
        }
    }

    /**
     * Sanitize audit data to remove sensitive information.
     */
    protected function sanitizeAuditData(array $data): string
    {
        $sensitiveFields = [
            'password',
            'api_key',
            'secret_key',
            'private_key',
            'card_number',
            'cvv',
            'pin',
            'token',
            'access_token',
            'refresh_token',
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeAuditData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return json_encode($sanitized);
    }

    /**
     * Sanitize webhook data for audit logging.
     */
    protected function sanitizeWebhookData(array $webhookData): array
    {
        $sanitized = [];
        
        foreach ($webhookData as $key => $value) {
            // Keep important fields but sanitize sensitive ones
            if (in_array(strtolower($key), ['signature', 'token', 'key'])) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeWebhookData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Calculate changes between old and new data.
     */
    protected function calculateChanges(array $oldData, array $newData): string
    {
        $changes = [];

        // Find added fields
        foreach ($newData as $key => $value) {
            if (!array_key_exists($key, $oldData)) {
                $changes[] = [
                    'field' => $key,
                    'action' => 'added',
                    'old_value' => null,
                    'new_value' => $value,
                ];
            } elseif ($oldData[$key] !== $value) {
                $changes[] = [
                    'field' => $key,
                    'action' => 'modified',
                    'old_value' => $oldData[$key],
                    'new_value' => $value,
                ];
            }
        }

        // Find removed fields
        foreach ($oldData as $key => $value) {
            if (!array_key_exists($key, $newData)) {
                $changes[] = [
                    'field' => $key,
                    'action' => 'removed',
                    'old_value' => $value,
                    'new_value' => null,
                ];
            }
        }

        return json_encode($changes);
    }
}