<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\PaymentEncryptionService;
use App\Services\PaymentSecurityService;

class ApiKeyManagementService
{
    protected PaymentEncryptionService $encryptionService;
    protected PaymentSecurityService $securityService;

    public function __construct(
        PaymentEncryptionService $encryptionService,
        PaymentSecurityService $securityService
    ) {
        $this->encryptionService = $encryptionService;
        $this->securityService = $securityService;
    }

    /**
     * Store encrypted API key securely.
     */
    public function storeApiKey(
        string $provider,
        string $apiKey,
        string $environment = 'production',
        ?int $storeId = null
    ): array {
        try {
            $encryptedKey = $this->encryptionService->encryptApiKey($apiKey);
            $keyHash = $this->encryptionService->hashSensitiveData($apiKey);
            
            $keyData = [
                'provider' => $provider,
                'environment' => $environment,
                'store_id' => $storeId,
                'encrypted_key' => $encryptedKey,
                'key_hash' => $keyHash,
                'created_at' => now(),
                'updated_at' => now(),
                'expires_at' => $this->calculateExpiryDate(),
                'is_active' => true,
                'rotation_count' => 0,
            ];

            $keyId = DB::table('api_keys')->insertGetId($keyData);

            // Log API key storage
            $this->securityService->logSecurityEvent(
                'api_key_stored',
                'info',
                [
                    'provider' => $provider,
                    'environment' => $environment,
                    'store_id' => $storeId,
                    'key_id' => $keyId,
                ]
            );

            // Clear the original key from memory
            $this->encryptionService->secureWipe($apiKey);

            return [
                'success' => true,
                'key_id' => $keyId,
                'expires_at' => $keyData['expires_at'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to store API key', [
                'provider' => $provider,
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve and decrypt API key.
     */
    public function getApiKey(
        string $provider,
        string $environment = 'production',
        ?int $storeId = null
    ): ?string {
        try {
            $query = DB::table('api_keys')
                ->where('provider', $provider)
                ->where('environment', $environment)
                ->where('is_active', true);

            if ($storeId) {
                $query->where('store_id', $storeId);
            } else {
                $query->whereNull('store_id');
            }

            $keyRecord = $query->first();

            if (!$keyRecord) {
                return null;
            }

            // Check if key is expired
            if ($keyRecord->expires_at && Carbon::parse($keyRecord->expires_at)->isPast()) {
                $this->securityService->logSecurityEvent(
                    'expired_api_key_access_attempt',
                    'warning',
                    [
                        'provider' => $provider,
                        'environment' => $environment,
                        'key_id' => $keyRecord->id,
                        'expired_at' => $keyRecord->expires_at,
                    ]
                );

                return null;
            }

            // Decrypt and return the key
            $decryptedKey = $this->encryptionService->decryptApiKey($keyRecord->encrypted_key);

            // Log key access
            $this->securityService->logSecurityEvent(
                'api_key_accessed',
                'info',
                [
                    'provider' => $provider,
                    'environment' => $environment,
                    'key_id' => $keyRecord->id,
                ]
            );

            return $decryptedKey;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve API key', [
                'provider' => $provider,
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Rotate API key (create new version and deactivate old).
     */
    public function rotateApiKey(
        string $provider,
        string $newApiKey,
        string $environment = 'production',
        ?int $storeId = null
    ): array {
        try {
            DB::beginTransaction();

            // Deactivate old key
            $oldKeyQuery = DB::table('api_keys')
                ->where('provider', $provider)
                ->where('environment', $environment)
                ->where('is_active', true);

            if ($storeId) {
                $oldKeyQuery->where('store_id', $storeId);
            } else {
                $oldKeyQuery->whereNull('store_id');
            }

            $oldKey = $oldKeyQuery->first();
            $rotationCount = $oldKey ? $oldKey->rotation_count + 1 : 1;

            if ($oldKey) {
                DB::table('api_keys')
                    ->where('id', $oldKey->id)
                    ->update([
                        'is_active' => false,
                        'deactivated_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            // Store new key
            $result = $this->storeApiKey($provider, $newApiKey, $environment, $storeId);
            
            if ($result['success']) {
                // Update rotation count
                DB::table('api_keys')
                    ->where('id', $result['key_id'])
                    ->update(['rotation_count' => $rotationCount]);

                DB::commit();

                $this->securityService->logSecurityEvent(
                    'api_key_rotated',
                    'info',
                    [
                        'provider' => $provider,
                        'environment' => $environment,
                        'old_key_id' => $oldKey?->id,
                        'new_key_id' => $result['key_id'],
                        'rotation_count' => $rotationCount,
                    ]
                );

                return [
                    'success' => true,
                    'old_key_id' => $oldKey?->id,
                    'new_key_id' => $result['key_id'],
                    'rotation_count' => $rotationCount,
                ];
            } else {
                DB::rollBack();
                return $result;
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to rotate API key', [
                'provider' => $provider,
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Deactivate API key.
     */
    public function deactivateApiKey(int $keyId, string $reason = 'Manual deactivation'): bool
    {
        try {
            $updated = DB::table('api_keys')
                ->where('id', $keyId)
                ->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                    'deactivation_reason' => $reason,
                    'updated_at' => now(),
                ]);

            if ($updated) {
                $this->securityService->logSecurityEvent(
                    'api_key_deactivated',
                    'info',
                    [
                        'key_id' => $keyId,
                        'reason' => $reason,
                    ]
                );
            }

            return $updated > 0;

        } catch (\Exception $e) {
            Log::error('Failed to deactivate API key', [
                'key_id' => $keyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List API keys for a provider.
     */
    public function listApiKeys(
        string $provider,
        ?string $environment = null,
        ?int $storeId = null,
        bool $activeOnly = true
    ): array {
        try {
            $query = DB::table('api_keys')
                ->select([
                    'id',
                    'provider',
                    'environment',
                    'store_id',
                    'created_at',
                    'updated_at',
                    'expires_at',
                    'deactivated_at',
                    'is_active',
                    'rotation_count',
                    'deactivation_reason'
                ])
                ->where('provider', $provider);

            if ($environment) {
                $query->where('environment', $environment);
            }

            if ($storeId !== null) {
                $query->where('store_id', $storeId);
            }

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            $keys = $query->orderBy('created_at', 'desc')->get()->toArray();

            return [
                'success' => true,
                'keys' => $keys,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to list API keys', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'keys' => [],
            ];
        }
    }

    /**
     * Check if API keys need rotation based on age.
     */
    public function checkKeysForRotation(): array
    {
        try {
            $rotationThreshold = now()->subDays(
                config('xendit.security.encryption.key_rotation_days', 90)
            );

            $keysNeedingRotation = DB::table('api_keys')
                ->select(['id', 'provider', 'environment', 'store_id', 'created_at'])
                ->where('is_active', true)
                ->where('created_at', '<', $rotationThreshold)
                ->get();

            $results = [];
            foreach ($keysNeedingRotation as $key) {
                $results[] = [
                    'key_id' => $key->id,
                    'provider' => $key->provider,
                    'environment' => $key->environment,
                    'store_id' => $key->store_id,
                    'age_days' => Carbon::parse($key->created_at)->diffInDays(now()),
                    'needs_rotation' => true,
                ];
            }

            if (!empty($results)) {
                $this->securityService->logSecurityEvent(
                    'api_keys_need_rotation',
                    'warning',
                    [
                        'keys_count' => count($results),
                        'rotation_threshold_days' => config('xendit.security.encryption.key_rotation_days', 90),
                    ]
                );
            }

            return [
                'success' => true,
                'keys_needing_rotation' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to check keys for rotation', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'keys_needing_rotation' => [],
            ];
        }
    }

    /**
     * Validate API key integrity.
     */
    public function validateApiKeyIntegrity(int $keyId): array
    {
        try {
            $keyRecord = DB::table('api_keys')->where('id', $keyId)->first();

            if (!$keyRecord) {
                return [
                    'valid' => false,
                    'error' => 'API key not found',
                ];
            }

            // Try to decrypt the key
            $decryptedKey = $this->encryptionService->decryptApiKey($keyRecord->encrypted_key);
            
            // Verify hash matches
            $computedHash = $this->encryptionService->hashSensitiveData($decryptedKey);
            $hashMatches = hash_equals($keyRecord->key_hash, $computedHash);

            // Clear decrypted key from memory
            $this->encryptionService->secureWipe($decryptedKey);

            if (!$hashMatches) {
                $this->securityService->logSecurityEvent(
                    'api_key_integrity_failure',
                    'critical',
                    [
                        'key_id' => $keyId,
                        'provider' => $keyRecord->provider,
                    ]
                );
            }

            return [
                'valid' => $hashMatches,
                'key_id' => $keyId,
                'provider' => $keyRecord->provider,
                'environment' => $keyRecord->environment,
                'hash_matches' => $hashMatches,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to validate API key integrity', [
                'key_id' => $keyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate expiry date for API keys.
     */
    protected function calculateExpiryDate(): Carbon
    {
        $rotationDays = config('xendit.security.encryption.key_rotation_days', 90);
        return now()->addDays($rotationDays);
    }

    /**
     * Get API key management statistics.
     */
    public function getApiKeyStats(): array
    {
        try {
            $stats = DB::table('api_keys')
                ->selectRaw('
                    provider,
                    environment,
                    COUNT(*) as total_keys,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_keys,
                    AVG(rotation_count) as avg_rotations,
                    MAX(created_at) as latest_key_date,
                    MIN(created_at) as oldest_key_date
                ')
                ->groupBy(['provider', 'environment'])
                ->get();

            return [
                'success' => true,
                'stats' => $stats->toArray(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get API key stats', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => [],
            ];
        }
    }
}