<?php

namespace App\Jobs;

use App\Models\SubscriptionPayment;
use App\Models\Subscription;
use App\Services\SubscriptionPaymentNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_FAILURE = 'failure';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_SUSPENSION = 'suspension';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $notificationType,
        public ?string $subscriptionPaymentId = null,
        public ?string $subscriptionId = null,
        public array $options = []
    ) {
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(SubscriptionPaymentNotificationService $notificationService): void
    {
        try {
            switch ($this->notificationType) {
                case self::TYPE_CONFIRMATION:
                    $this->handlePaymentConfirmation($notificationService);
                    break;

                case self::TYPE_FAILURE:
                    $this->handlePaymentFailure($notificationService);
                    break;

                case self::TYPE_REMINDER:
                    $this->handlePaymentReminder($notificationService);
                    break;

                case self::TYPE_SUSPENSION:
                    $this->handleSubscriptionSuspension($notificationService);
                    break;

                default:
                    throw new \InvalidArgumentException("Unknown notification type: {$this->notificationType}");
            }

            Log::info('Payment notification job completed successfully', [
                'type' => $this->notificationType,
                'subscription_payment_id' => $this->subscriptionPaymentId,
                'subscription_id' => $this->subscriptionId,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment notification job failed', [
                'type' => $this->notificationType,
                'subscription_payment_id' => $this->subscriptionPaymentId,
                'subscription_id' => $this->subscriptionId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle payment confirmation notification.
     */
    private function handlePaymentConfirmation(SubscriptionPaymentNotificationService $notificationService): void
    {
        if (!$this->subscriptionPaymentId) {
            throw new \InvalidArgumentException('Subscription payment ID is required for confirmation notification');
        }

        $subscriptionPayment = SubscriptionPayment::findOrFail($this->subscriptionPaymentId);
        
        $success = $notificationService->sendPaymentConfirmation($subscriptionPayment);
        
        if (!$success) {
            throw new \RuntimeException('Failed to send payment confirmation notification');
        }
    }

    /**
     * Handle payment failure notification.
     */
    private function handlePaymentFailure(SubscriptionPaymentNotificationService $notificationService): void
    {
        if (!$this->subscriptionPaymentId) {
            throw new \InvalidArgumentException('Subscription payment ID is required for failure notification');
        }

        $subscriptionPayment = SubscriptionPayment::findOrFail($this->subscriptionPaymentId);
        
        $failureReason = $this->options['failure_reason'] ?? null;
        $isRetryAttempt = $this->options['is_retry_attempt'] ?? false;
        $retryCount = $this->options['retry_count'] ?? 0;

        $success = $notificationService->sendPaymentFailure(
            $subscriptionPayment,
            $failureReason,
            $isRetryAttempt,
            $retryCount
        );
        
        if (!$success) {
            throw new \RuntimeException('Failed to send payment failure notification');
        }
    }

    /**
     * Handle payment reminder notification.
     */
    private function handlePaymentReminder(SubscriptionPaymentNotificationService $notificationService): void
    {
        if (!$this->subscriptionId) {
            throw new \InvalidArgumentException('Subscription ID is required for reminder notification');
        }

        $subscription = Subscription::findOrFail($this->subscriptionId);
        
        $upcomingPayment = null;
        if (!empty($this->options['upcoming_payment_id'])) {
            $upcomingPayment = SubscriptionPayment::find($this->options['upcoming_payment_id']);
        }

        $daysUntilExpiration = $this->options['days_until_expiration'] ?? 7;

        $success = $notificationService->sendPaymentReminder(
            $subscription,
            $upcomingPayment,
            $daysUntilExpiration
        );
        
        if (!$success) {
            throw new \RuntimeException('Failed to send payment reminder notification');
        }
    }

    /**
     * Handle subscription suspension notification.
     */
    private function handleSubscriptionSuspension(SubscriptionPaymentNotificationService $notificationService): void
    {
        if (!$this->subscriptionId) {
            throw new \InvalidArgumentException('Subscription ID is required for suspension notification');
        }

        $subscription = Subscription::findOrFail($this->subscriptionId);
        
        $failedPayment = null;
        if (!empty($this->options['failed_payment_id'])) {
            $failedPayment = SubscriptionPayment::find($this->options['failed_payment_id']);
        }

        $suspensionReason = $this->options['suspension_reason'] ?? null;
        $gracePeriodDays = $this->options['grace_period_days'] ?? 7;

        $success = $notificationService->sendSubscriptionSuspended(
            $subscription,
            $failedPayment,
            $suspensionReason,
            $gracePeriodDays
        );
        
        if (!$success) {
            throw new \RuntimeException('Failed to send subscription suspension notification');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment notification job failed permanently', [
            'type' => $this->notificationType,
            'subscription_payment_id' => $this->subscriptionPaymentId,
            'subscription_id' => $this->subscriptionId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Static helper methods to dispatch notification jobs.
     */
    public static function dispatchPaymentConfirmation(SubscriptionPayment $subscriptionPayment): void
    {
        self::dispatch(
            self::TYPE_CONFIRMATION,
            $subscriptionPayment->id
        );
    }

    public static function dispatchPaymentFailure(
        SubscriptionPayment $subscriptionPayment,
        ?string $failureReason = null,
        bool $isRetryAttempt = false,
        int $retryCount = 0
    ): void {
        self::dispatch(
            self::TYPE_FAILURE,
            $subscriptionPayment->id,
            null,
            [
                'failure_reason' => $failureReason,
                'is_retry_attempt' => $isRetryAttempt,
                'retry_count' => $retryCount,
            ]
        );
    }

    public static function dispatchPaymentReminder(
        Subscription $subscription,
        ?SubscriptionPayment $upcomingPayment = null,
        int $daysUntilExpiration = 7
    ): void {
        self::dispatch(
            self::TYPE_REMINDER,
            null,
            $subscription->id,
            [
                'upcoming_payment_id' => $upcomingPayment?->id,
                'days_until_expiration' => $daysUntilExpiration,
            ]
        );
    }

    public static function dispatchSubscriptionSuspension(
        Subscription $subscription,
        ?SubscriptionPayment $failedPayment = null,
        ?string $suspensionReason = null,
        int $gracePeriodDays = 7
    ): void {
        self::dispatch(
            self::TYPE_SUSPENSION,
            null,
            $subscription->id,
            [
                'failed_payment_id' => $failedPayment?->id,
                'suspension_reason' => $suspensionReason,
                'grace_period_days' => $gracePeriodDays,
            ]
        );
    }
}