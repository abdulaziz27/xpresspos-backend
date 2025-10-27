<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentEncryptionService
{
    private string $algorithm;
    private int $keyRotationDays;

    public function __construct()
    {
        $this->algorithm = config('xendit.security.encryption.algorithm', 'AES-256-CBC');
        $this->keyRotationDays = config('xendit.security.encryption.key_rotation_days', 90);
    }

    /**
     * Encrypt sensitive payment data.
     */
    public function encryptSensitiveData(array $data): array
    {
        $encrypted = [];
        
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key)) {
                $encrypted[$key] = $this->encrypt($value);
            } else {
                $encrypted[$key] = $value;
            }
        }

        return $encrypted;
    }

    /**
     * Decrypt sensitive payment data.
     */
    public function decryptSensitiveData(array $data): array
    {
        $decrypted = [];
        
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key) && $this->isEncrypted($value)) {
                $decrypted[$key] = $this->decrypt($value);
            } else {
                $decrypted[$key] = $value;
            }
        }

        return $decrypted;
    }

    /**
     * Encrypt a single value.
     */
    public function encrypt(string $value): string
    {
        try {
            $encryptionKey = $this->getCurrentEncryptionKey();
            $encrypted = Crypt::encrypt($value);
            
            // Add metadata for key rotation tracking
            $metadata = [
                'encrypted_at' => now()->toISOString(),
                'key_version' => $this->getCurrentKeyVersion(),
                'algorithm' => $this->algorithm,
            ];

            return base64_encode(json_encode([
                'data' => $encrypted,
                'metadata' => $metadata,
            ]));

        } catch (\Exception $e) {
            Log::error('Failed to encrypt payment data', [
                'error' => $e->getMessage(),
                'algorithm' => $this->algorithm,
            ]);

            throw new \Exception('Encryption failed');
        }
    }

    /**
     * Decrypt a single value.
     */
    public function decrypt(string $encryptedValue): string
    {
        try {
            $decoded = json_decode(base64_decode($encryptedValue), true);
            
            if (!$decoded || !isset($decoded['data'])) {
                // Fallback for legacy encrypted data without metadata
                return Crypt::decrypt($encryptedValue);
            }

            $metadata = $decoded['metadata'] ?? [];
            
            // Check if key rotation is needed
            if ($this->needsKeyRotation($metadata)) {
                Log::info('Encrypted data needs key rotation', [
                    'key_version' => $metadata['key_version'] ?? 'unknown',
                    'encrypted_at' => $metadata['encrypted_at'] ?? 'unknown',
                ]);
            }

            return Crypt::decrypt($decoded['data']);

        } catch (\Exception $e) {
            Log::error('Failed to decrypt payment data', [
                'error' => $e->getMessage(),
                'encrypted_value_length' => strlen($encryptedValue),
            ]);

            throw new \Exception('Decryption failed');
        }
    }

    /**
     * Encrypt API keys and tokens.
     */
    public function encryptApiKey(string $apiKey): string
    {
        return $this->encrypt($apiKey);
    }

    /**
     * Decrypt API keys and tokens.
     */
    public function decryptApiKey(string $encryptedApiKey): string
    {
        return $this->decrypt($encryptedApiKey);
    }

    /**
     * Hash sensitive data for comparison without decryption.
     */
    public function hashSensitiveData(string $data): string
    {
        return hash('sha256', $data . config('app.key'));
    }

    /**
     * Verify hashed sensitive data.
     */
    public function verifySensitiveDataHash(string $data, string $hash): bool
    {
        return hash_equals($hash, $this->hashSensitiveData($data));
    }

    /**
     * Check if a field contains sensitive data.
     */
    protected function isSensitiveField(string $fieldName): bool
    {
        $sensitiveFields = [
            'api_key',
            'webhook_token',
            'secret_key',
            'private_key',
            'card_number',
            'cvv',
            'pin',
            'password',
            'token',
            'access_token',
            'refresh_token',
            'bank_account',
            'account_number',
            'routing_number',
            'ssn',
            'tax_id',
        ];

        $fieldLower = strtolower($fieldName);
        
        foreach ($sensitiveFields as $sensitiveField) {
            if (str_contains($fieldLower, $sensitiveField)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value is encrypted (has our encryption format).
     */
    protected function isEncrypted(string $value): bool
    {
        // Check if it's base64 encoded JSON with our structure
        $decoded = json_decode(base64_decode($value), true);
        
        return $decoded && isset($decoded['data']) && isset($decoded['metadata']);
    }

    /**
     * Get current encryption key version for rotation tracking.
     */
    protected function getCurrentKeyVersion(): string
    {
        $keyDate = Cache::remember('encryption_key_version', 86400, function () {
            return now()->format('Y-m-d');
        });

        return hash('sha256', config('app.key') . $keyDate);
    }

    /**
     * Get current encryption key (for future key rotation implementation).
     */
    protected function getCurrentEncryptionKey(): string
    {
        // For now, use Laravel's app key
        // In future, this could implement proper key rotation
        return config('app.key');
    }

    /**
     * Check if encrypted data needs key rotation.
     */
    protected function needsKeyRotation(array $metadata): bool
    {
        if (!isset($metadata['encrypted_at'])) {
            return true; // Legacy data without metadata
        }

        $encryptedAt = Carbon::parse($metadata['encrypted_at']);
        $rotationThreshold = now()->subDays($this->keyRotationDays);

        return $encryptedAt->lt($rotationThreshold);
    }

    /**
     * Rotate encryption keys for old data.
     */
    public function rotateEncryptionKeys(): array
    {
        $rotated = 0;
        $failed = 0;

        try {
            // Find encrypted data that needs rotation
            // This would typically query the database for old encrypted data
            
            Log::info('Starting encryption key rotation', [
                'rotation_threshold_days' => $this->keyRotationDays,
            ]);

            // TODO: Implement actual key rotation logic
            // This would involve:
            // 1. Querying for old encrypted data
            // 2. Decrypting with old key
            // 3. Re-encrypting with new key
            // 4. Updating database records

            Log::info('Encryption key rotation completed', [
                'rotated_count' => $rotated,
                'failed_count' => $failed,
            ]);

        } catch (\Exception $e) {
            Log::error('Encryption key rotation failed', [
                'error' => $e->getMessage(),
                'rotated_count' => $rotated,
                'failed_count' => $failed,
            ]);
        }

        return [
            'rotated' => $rotated,
            'failed' => $failed,
        ];
    }

    /**
     * Securely wipe sensitive data from memory.
     */
    public function secureWipe(string &$data): void
    {
        // Overwrite the string with random data multiple times
        $length = strlen($data);
        
        for ($i = 0; $i < 3; $i++) {
            $data = str_repeat(chr(random_int(0, 255)), $length);
        }
        
        // Finally set to empty
        $data = '';
    }

    /**
     * Generate secure random token for API keys.
     */
    public function generateSecureToken(int $length = 32): string
    {
        return Str::random($length);
    }

    /**
     * Validate encryption configuration.
     */
    public function validateEncryptionConfig(): array
    {
        $issues = [];

        // Check if app key is set
        if (empty(config('app.key'))) {
            $issues[] = 'Application key is not set';
        }

        // Check key length for AES-256
        $appKey = config('app.key');
        if ($appKey && strlen(base64_decode(substr($appKey, 7))) !== 32) {
            $issues[] = 'Application key is not 256-bit for AES-256 encryption';
        }

        // Check if encryption is enabled
        if (!config('app.cipher')) {
            $issues[] = 'Encryption cipher is not configured';
        }

        // Check algorithm configuration
        if ($this->algorithm !== config('app.cipher')) {
            $issues[] = 'Encryption algorithm mismatch between app and xendit config';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Get encryption statistics for monitoring.
     */
    public function getEncryptionStats(): array
    {
        return [
            'algorithm' => $this->algorithm,
            'key_rotation_days' => $this->keyRotationDays,
            'current_key_version' => $this->getCurrentKeyVersion(),
            'config_validation' => $this->validateEncryptionConfig(),
        ];
    }
}