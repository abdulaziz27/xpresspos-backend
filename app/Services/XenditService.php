<?php

namespace App\Services;

use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceCallback;
use Xendit\PaymentRequest\PaymentRequestApi;
use Xendit\PaymentRequest\PaymentRequestParameters;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\ApiKeyManagementService;
use App\Services\PaymentEncryptionService;
use App\Services\PaymentSecurityService;

class XenditService
{
    private ?InvoiceApi $invoiceApi = null;
    private ?PaymentRequestApi $paymentRequestApi = null;
    private ?string $apiKey = null;
    private ?string $webhookToken = null;
    private ApiKeyManagementService $keyManager;
    private PaymentEncryptionService $encryptionService;
    private PaymentSecurityService $securityService;

    public function __construct(
        ApiKeyManagementService $keyManager,
        PaymentEncryptionService $encryptionService,
        PaymentSecurityService $securityService
    ) {
        $this->keyManager = $keyManager;
        $this->encryptionService = $encryptionService;
        $this->securityService = $securityService;
        
        $this->initializeApiCredentials();
        $this->configureXenditClient();
    }

    /**
     * Initialize API credentials from secure storage.
     */
    private function initializeApiCredentials(): void
    {
        $environment = config('xendit.is_production') ? 'production' : 'sandbox';
        
        // Try to get API key from secure storage first
        try {
            $this->apiKey = $this->keyManager->getApiKey('xendit', $environment);
        } catch (\Exception $e) {
            // If secure storage fails, use null and fallback to config
            $this->apiKey = null;
        }
        
        // Fallback to config if not found in secure storage
        if (!$this->apiKey) {
            $this->apiKey = config('xendit.api_key');
            
            // Store in secure storage for future use (only if we have a valid key)
            if ($this->apiKey) {
                try {
                    $this->keyManager->storeApiKey('xendit', $this->apiKey, $environment);
                } catch (\Exception $e) {
                    // Ignore storage errors, continue with config key
                    Log::warning('Failed to store Xendit API key securely', ['error' => $e->getMessage()]);
                }
            }
        }
        
        // Get webhook token (also store securely)
        try {
            $this->webhookToken = $this->keyManager->getApiKey('xendit_webhook', $environment);
        } catch (\Exception $e) {
            $this->webhookToken = null;
        }
        
        if (!$this->webhookToken) {
            $this->webhookToken = config('xendit.webhook_token');
            
            if ($this->webhookToken) {
                try {
                    $this->keyManager->storeApiKey('xendit_webhook', $this->webhookToken, $environment);
                } catch (\Exception $e) {
                    // Ignore storage errors, continue with config token
                    Log::warning('Failed to store Xendit webhook token securely', ['error' => $e->getMessage()]);
                }
            }
        }
        
        // Set default values if still empty
        if (empty($this->apiKey)) {
            $this->apiKey = 'dummy_key_for_development';
            Log::warning('Xendit API key is not configured, using dummy key');
        }
        
        if (empty($this->webhookToken)) {
            $this->webhookToken = 'dummy_token_for_development';
            Log::warning('Xendit webhook token is not configured, using dummy token');
        }
    }

    /**
     * Configure Xendit client with security logging.
     */
    private function configureXenditClient(): void
    {
        try {
            // Only configure if we have a real API key
            if ($this->apiKey && $this->apiKey !== 'dummy_key_for_development') {
                Configuration::setXenditKey($this->apiKey);
                $this->invoiceApi = new InvoiceApi();
                $this->paymentRequestApi = new PaymentRequestApi();
                
                $this->securityService->logSecurityEvent(
                    'xendit_client_configured',
                    'info',
                    [
                        'environment' => config('xendit.is_production') ? 'production' : 'sandbox',
                    ]
                );
            } else {
                Log::warning('Xendit client not configured due to missing API key');
            }
            
        } catch (\Exception $e) {
            $this->securityService->logSecurityEvent(
                'xendit_client_configuration_failed',
                'error',
                [
                    'error' => $e->getMessage(),
                ]
            );
            
            Log::error('Failed to configure Xendit client', [
                'error' => $e->getMessage(),
                'has_api_key' => !empty($this->apiKey) && $this->apiKey !== 'dummy_key_for_development'
            ]);
        }
    }

    /**
     * Create invoice for subscription payment
     */
    public function createInvoice(array $data): array
    {
        // Check if we're in development mode without real API key or client not configured
        if (!$this->apiKey || $this->apiKey === 'dummy_key_for_development' || !$this->invoiceApi) {
            return $this->createDummyInvoice($data);
        }

        try {
            $externalId = $this->generateExternalId($data['type'] ?? 'subscription');
            
            $invoiceRequest = new CreateInvoiceRequest([
                'external_id' => $externalId,
                'amount' => $data['amount'],
                'description' => $data['description'] ?? 'XpressPOS Subscription Payment',
                'invoice_duration' => config('xendit.invoice_expiry_hours') * 3600, // Convert to seconds
                'customer' => [
                    'given_names' => $data['customer_name'] ?? '',
                    'email' => $data['customer_email'] ?? '',
                    'mobile_number' => $data['customer_phone'] ?? '',
                ],
                'customer_notification_preference' => [
                    'invoice_created' => ['email', 'sms'],
                    'invoice_reminder' => ['email', 'sms'],
                    'invoice_paid' => ['email', 'sms'],
                    'invoice_expired' => ['email', 'sms']
                ],
                'success_redirect_url' => $data['success_redirect_url'] ?? config('xendit.invoice.success_redirect_url'),
                'failure_redirect_url' => $data['failure_redirect_url'] ?? config('xendit.invoice.failure_redirect_url'),
                'currency' => config('xendit.currency', 'IDR'),
                'items' => [
                    [
                        'name' => $data['item_name'] ?? 'XpressPOS Subscription',
                        'quantity' => 1,
                        'price' => $data['amount'],
                        'category' => 'Software Subscription'
                    ]
                ],
                'fees' => [],
                'payment_methods' => $this->getAvailablePaymentMethods($data['payment_methods'] ?? []),
            ]);

            $response = $this->invoiceApi->createInvoice($invoiceRequest);

            Log::info('Xendit invoice created successfully', [
                'external_id' => $externalId,
                'invoice_id' => $response['id'],
                'amount' => $data['amount']
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $response['id'],
                    'external_id' => $externalId,
                    'invoice_url' => $response['invoice_url'],
                    'amount' => $response['amount'],
                    'status' => $response['status'],
                    'expiry_date' => $response['expiry_date'],
                    'created' => $response['created'],
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create Xendit invoice', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create dummy invoice for development/testing
     */
    private function createDummyInvoice(array $data): array
    {
        $externalId = $this->generateExternalId($data['type'] ?? 'subscription');
        $invoiceId = 'dummy_' . uniqid();
        
        Log::info('Created dummy Xendit invoice for development', [
            'external_id' => $externalId,
            'invoice_id' => $invoiceId,
            'amount' => $data['amount']
        ]);

        return [
            'success' => true,
            'data' => [
                'id' => $invoiceId,
                'external_id' => $externalId,
                'invoice_url' => route('landing.payment.success') . '?dummy=true',
                'amount' => $data['amount'],
                'status' => 'PENDING',
                'expiry_date' => now()->addHours(24)->toISOString(),
                'created' => now()->toISOString(),
            ]
        ];
    }

    /**
     * Get invoice details by ID
     */
    public function getInvoice(string $invoiceId): array
    {
        // Handle dummy invoices
        if (str_starts_with($invoiceId, 'dummy_') || !$this->invoiceApi) {
            return [
                'success' => true,
                'data' => [
                    'id' => $invoiceId,
                    'external_id' => 'dummy_external_' . time(),
                    'status' => 'PAID',
                    'amount' => 599000,
                    'paid_amount' => 599000,
                    'payment_method' => 'BANK_TRANSFER',
                    'payment_channel' => 'BCA',
                    'payment_destination' => null,
                    'paid_at' => now()->toISOString(),
                    'created' => now()->subHour()->toISOString(),
                    'updated' => now()->toISOString(),
                    'expiry_date' => now()->addHours(24)->toISOString(),
                ]
            ];
        }

        try {
            $response = $this->invoiceApi->getInvoiceById($invoiceId);

            return [
                'success' => true,
                'data' => [
                    'id' => $response['id'],
                    'external_id' => $response['external_id'],
                    'status' => $response['status'],
                    'amount' => $response['amount'],
                    'paid_amount' => $response['paid_amount'] ?? 0,
                    'payment_method' => $response['payment_method'] ?? null,
                    'payment_channel' => $response['payment_channel'] ?? null,
                    'payment_destination' => $response['payment_destination'] ?? null,
                    'paid_at' => $response['paid_at'] ?? null,
                    'created' => $response['created'],
                    'updated' => $response['updated'],
                    'expiry_date' => $response['expiry_date'],
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get Xendit invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhook(string $payload, string $signature): bool
    {
        // In development mode, always return true for dummy tokens
        if (!$this->webhookToken || $this->webhookToken === 'dummy_token_for_development') {
            Log::info('Webhook validation skipped in development mode');
            return true;
        }

        try {
            $expectedSignature = hash_hmac('sha256', $payload, $this->webhookToken);
            
            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('Failed to validate webhook signature', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Process webhook callback
     */
    public function processWebhookCallback(array $callbackData): array
    {
        try {
            $invoiceCallback = new InvoiceCallback($callbackData);
            
            Log::info('Processing Xendit webhook callback', [
                'invoice_id' => $invoiceCallback->getId(),
                'external_id' => $invoiceCallback->getExternalId(),
                'status' => $invoiceCallback->getStatus(),
                'amount' => $invoiceCallback->getAmount(),
                'paid_amount' => $invoiceCallback->getPaidAmount()
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $invoiceCallback->getId(),
                    'external_id' => $invoiceCallback->getExternalId(),
                    'status' => $invoiceCallback->getStatus(),
                    'amount' => $invoiceCallback->getAmount(),
                    'paid_amount' => $invoiceCallback->getPaidAmount(),
                    'payment_method' => $invoiceCallback->getPaymentMethod(),
                    'payment_channel' => $invoiceCallback->getPaymentChannel(),
                    'payment_destination' => $invoiceCallback->getPaymentDestination(),
                    'paid_at' => $invoiceCallback->getPaidAt(),
                    'created' => $invoiceCallback->getCreated(),
                    'updated' => $invoiceCallback->getUpdated(),
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process webhook callback', [
                'error' => $e->getMessage(),
                'callback_data' => $callbackData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create renewal invoice for subscription (manual recurring)
     * Since RecurringPayment API is not available, we'll create invoices manually for renewals
     */
    public function createRenewalInvoice(array $data): array
    {
        // Use the same createInvoice method but with renewal-specific external ID
        $renewalData = array_merge($data, [
            'type' => 'renewal',
            'description' => $data['description'] ?? 'XpressPOS Subscription Renewal'
        ]);
        
        return $this->createInvoice($renewalData);
    }

    /**
     * Schedule next renewal (this would be handled by Laravel scheduler)
     * This method returns the data needed to schedule the next renewal
     */
    public function getNextRenewalData(array $subscriptionData): array
    {
        $billingCycle = $subscriptionData['billing_cycle'] ?? 'monthly';
        $currentEndDate = Carbon::parse($subscriptionData['ends_at']);
        
        $nextRenewalDate = match($billingCycle) {
            'monthly' => $currentEndDate->addMonth(),
            'annual' => $currentEndDate->addYear(),
            default => $currentEndDate->addMonth()
        };
        
        return [
            'next_renewal_date' => $nextRenewalDate->toISOString(),
            'amount' => $subscriptionData['amount'],
            'billing_cycle' => $billingCycle,
            'subscription_id' => $subscriptionData['subscription_id'],
            'customer_email' => $subscriptionData['customer_email'],
            'customer_name' => $subscriptionData['customer_name'],
        ];
    }

    /**
     * Process payment request callback (alternative to recurring payments)
     */
    public function processPaymentRequestCallback(array $callbackData): array
    {
        try {
            Log::info('Processing Xendit payment request webhook callback', [
                'payment_request_id' => $callbackData['id'] ?? null,
                'reference_id' => $callbackData['reference_id'] ?? null,
                'status' => $callbackData['status'] ?? null,
                'amount' => $callbackData['amount'] ?? null
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $callbackData['id'] ?? null,
                    'reference_id' => $callbackData['reference_id'] ?? null,
                    'status' => $callbackData['status'] ?? null,
                    'amount' => $callbackData['amount'] ?? null,
                    'payment_method' => $callbackData['payment_method'] ?? null,
                    'created' => $callbackData['created'] ?? null,
                    'updated' => $callbackData['updated'] ?? null,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process payment request webhook callback', [
                'error' => $e->getMessage(),
                'callback_data' => $callbackData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate unique external ID
     */
    private function generateExternalId(string $type = 'subscription'): string
    {
        $prefix = config('xendit.invoice.prefix', 'XPOS');
        $timestamp = now()->format('YmdHis');
        $random = Str::upper(Str::random(4));
        
        return "{$prefix}_{$type}_{$timestamp}_{$random}";
    }

    /**
     * Get available payment methods based on configuration
     */
    private function getAvailablePaymentMethods(array $requestedMethods = []): array
    {
        $allMethods = [
            'BANK_TRANSFER',
            'EWALLET',
            'RETAIL_OUTLET',
            'QR_CODE',
            'CREDIT_CARD'
        ];

        // If specific methods requested, filter them
        if (!empty($requestedMethods)) {
            return array_intersect($allMethods, $requestedMethods);
        }

        // Return all available methods
        return $allMethods;
    }

    /**
     * Get payment method details for display
     */
    public function getPaymentMethodDetails(): array
    {
        return config('xendit.payment_methods', []);
    }

    /**
     * Check if service is in production mode
     */
    public function isProduction(): bool
    {
        return config('xendit.is_production', false);
    }

    /**
     * Get webhook endpoints
     */
    public function getWebhookEndpoints(): array
    {
        return config('xendit.webhook_endpoints', []);
    }

    /**
     * Format amount for Xendit (ensure it's in the smallest currency unit)
     */
    public function formatAmount(float $amount): int
    {
        // For IDR, Xendit expects amount in Rupiah (not cents)
        // For other currencies, you might need to multiply by 100
        $currency = config('xendit.currency', 'IDR');
        
        if ($currency === 'IDR') {
            return (int) $amount;
        }
        
        // For other currencies, convert to smallest unit (cents)
        return (int) ($amount * 100);
    }

    /**
     * Parse amount from Xendit response
     */
    public function parseAmount(int $amount): float
    {
        $currency = config('xendit.currency', 'IDR');
        
        if ($currency === 'IDR') {
            return (float) $amount;
        }
        
        // For other currencies, convert from smallest unit (cents)
        return $amount / 100;
    }
}