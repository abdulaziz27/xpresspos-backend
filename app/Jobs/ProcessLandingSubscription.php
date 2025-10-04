<?php

namespace App\Jobs;

use App\Models\LandingSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLandingSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $subscriptionId
    ) {
    }

    public function handle(): void
    {
        $subscription = LandingSubscription::find($this->subscriptionId);

        if (! $subscription) {
            return;
        }

        $subscription->forceFill([
            'status' => 'processed',
            'stage' => $subscription->stage === 'new' ? 'contacted' : $subscription->stage,
            'processed_at' => now(),
        ])->save();

        Log::info('Landing subscription processed', [
            'subscription_id' => $subscription->id,
            'email' => $subscription->email,
        ]);
    }
}
