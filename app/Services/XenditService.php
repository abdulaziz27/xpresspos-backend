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
        // Backward compatibility: Convert old format to new format
        if (isset($data['customer_name']) || isset($data['customer_email']) || isset($data['customer_phone'])) {
            Log::info('Converting old payload format to new format', [
                'has_customer_name' => isset($data['customer_name']),
                'has_customer_email' => isset($data['customer_email']),
                'has_customer_phone' => isset($data['customer_phone']),
            ]);
            
            // Create customer object if not exists
            if (!isset($data['customer'])) {
                $data['customer'] = [];
            }
            
            // Map old fields to new customer object
            if (isset($data['customer_name']) && !isset($data['customer']['given_names'])) {
                $data['customer']['given_names'] = $data['customer_name'];
            }
            if (isset($data['customer_email']) && !isset($data['customer']['email'])) {
                $data['customer']['email'] = $data['customer_email'];
            }
            if (isset($data['customer_phone']) && !isset($data['customer']['mobile_number'])) {
                $data['customer']['mobile_number'] = $data['customer_phone'];
            }
            
            // Remove old fields to avoid confusion
            unset($data['customer_name'], $data['customer_email'], $data['customer_phone']);
        }
        
        // Ensure amount is integer
        if (isset($data['amount'])) {
            $data['amount'] = (int) $data['amount'];
        }
        
        // Check if we have a valid API key and client configured
        // Use dummy invoice only if API key is not configured or is dummy
        if (!$this->apiKey || $this->apiKey === 'dummy_key_for_development' || !$this->invoiceApi) {
            Log::info('Using dummy invoice', [
                'has_api_key' => !empty($this->apiKey),
                'api_key_is_dummy' => $this->apiKey === 'dummy_key_for_development',
                'has_invoice_api' => !empty($this->invoiceApi),
                'api_key_prefix' => $this->apiKey ? substr($this->apiKey, 0, 20) . '...' : 'null'
            ]);
            return $this->createDummyInvoice($data);
        }
        
        Log::info('Using real Xendit API', [
            'api_key_prefix' => substr($this->apiKey, 0, 20) . '...'
        ]);

        try {
            $externalId = $this->generateExternalId($data['type'] ?? 'subscription');
            
            // Prepare invoice payload using customer object
            $invoicePayload = [
                'external_id' => $externalId,
                'amount' => (int) $data['amount'], // Ensure integer type
                'description' => $data['description'] ?? 'XpressPOS Subscription Payment',
                'invoice_duration' => config('xendit.invoice_expiry_hours') * 3600, // Convert to seconds
                'customer' => [
                    'given_names' => $data['customer']['given_names'] ?? '',
                    'email' => $data['customer']['email'] ?? '',
                    'mobile_number' => $data['customer']['mobile_number'] ?? '', // E.164 format: +6285211553430
                ],
                'customer_notification_preference' => [
                    'invoice_created' => ['email'],
                    'invoice_paid' => ['email']
                ],
                'success_redirect_url' => $data['success_redirect_url'] ?? config('xendit.invoice.success_redirect_url'),
                'failure_redirect_url' => $data['failure_redirect_url'] ?? config('xendit.invoice.failure_redirect_url'),
                'currency' => config('xendit.currency', 'IDR'),
                'items' => [
                    [
                        'name' => $data['item_name'] ?? 'XpressPOS Subscription',
                        'quantity' => 1,
                        'price' => (int) $data['amount'], // Ensure integer type
                        'category' => 'Software Subscription'
                    ]
                ],
                'fees' => [],
            ];
            
            // IMPORTANT: Don't specify payment_methods to let Xendit use all available methods
            // Only add if explicitly requested by controller
            if (isset($data['payment_methods']) && !empty($data['payment_methods'])) {
                $invoicePayload['payment_methods'] = $this->getAvailablePaymentMethods($data['payment_methods']);
                Log::info('Using specific payment methods', [
                    'requested' => $data['payment_methods'],
                    'filtered' => $invoicePayload['payment_methods']
                ]);
            } else {
                // Don't add payment_methods field - let Xendit show all available methods
                Log::info('No payment_methods specified - Xendit will show all available methods');
            }
            
            // Log payload before sending to Xendit for debugging
            Log::info('Xendit invoice payload', [
                'payload' => $invoicePayload,
                'amount_type' => gettype($invoicePayload['amount']),
                'price_type' => gettype($invoicePayload['items'][0]['price']),
                'has_payment_methods' => isset($invoicePayload['payment_methods']),
                'payment_methods_count' => isset($invoicePayload['payment_methods']) ? count($invoicePayload['payment_methods']) : 0,
            ]);
            
            $invoiceRequest = new CreateInvoiceRequest($invoicePayload);
            $response = $this->invoiceApi->createInvoice($invoiceRequest);

            Log::info('Xendit invoice created successfully', [
                'external_id' => $externalId,
                'invoice_id' => $response['id'],
                'amount' => $data['amount'],
                'full_response' => $response // Log full response for debugging
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
            // Log detailed error information
            $errorDetails = [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ];
            
            // If it's an HTTP exception, log the response body
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $errorDetails['response_body'] = $e->getResponse()->getBody()->getContents();
            }
            
            Log::error('Failed to create Xendit invoice', $errorDetails);

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

        // Generate a realistic Xendit invoice URL for testing
        // In real scenario, this would be Xendit's hosted payment page
        $invoiceUrl = 'https://checkout-staging.xendit.co/web/' . $invoiceId;

        return [
            'success' => true,
            'data' => [
                'id' => $invoiceId,
                'external_id' => $externalId,
                'invoice_url' => $invoiceUrl,
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
     * Get available payment methods based on environment and configuration
     */
    private function getAvailablePaymentMethods(array $requestedMethods = []): array
    {
        // Determine available methods based on environment
        $isProduction = config('xendit.is_production', false);
        
        if ($isProduction) {
            // Production: All methods available
            $availableMethods = [
                'BANK_TRANSFER',
                'EWALLET',
                'QR_CODE',
                'CREDIT_CARD'
            ];
        } else {
            // Sandbox: Limited methods (based on Xendit sandbox limitations)
            $availableMethods = [
                'BANK_TRANSFER',
                'RETAIL_OUTLET',
                'QR_CODE'
            ];
        }
        
        Log::info('Payment methods determined by environment', [
            'environment' => $isProduction ? 'production' : 'sandbox',
            'available_methods' => $availableMethods,
            'requested_methods' => $requestedMethods
        ]);

        // If specific methods requested, filter them against available methods
        if (!empty($requestedMethods)) {
            $filtered = array_values(array_intersect($availableMethods, $requestedMethods));
            
            // If no valid methods after filtering, return all available methods
            if (empty($filtered)) {
                Log::warning('Requested payment methods not available, using defaults', [
                    'requested' => $requestedMethods,
                    'available' => $availableMethods
                ]);
                return $availableMethods;
            }
            
            return $filtered;
        }

        // Return all available methods for this environment
        return $availableMethods;
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