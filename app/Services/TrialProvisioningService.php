<?php

namespace App\Services;

use App\Models\LandingSubscription;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class TrialProvisioningService
{
    public function provisionFromLead(LandingSubscription $lead, ?User $actor = null): array
    {
        if ($lead->provisioned_store_id) {
            return [
                'success' => false,
                'message' => 'Lead already provisioned.',
            ];
        }

        $plan = $this->resolvePlan($lead->plan);

        if (!$plan) {
            return [
                'success' => false,
                'message' => 'No active plan available to provision trial workspace.',
            ];
        }

        $temporaryPassword = Str::random(12);

        try {
            DB::transaction(function () use ($lead, $plan, $temporaryPassword, $actor, &$store, &$owner, &$subscription) {
                $store = $this->createStore($lead);
                $owner = $this->createOwner($lead, $store, $temporaryPassword);
                $subscription = $this->createSubscription($lead, $store, $plan);
                $this->initialiseUsageRecords($subscription, $plan);

                $logEntry = [
                    'timestamp' => now()->toISOString(),
                    'message' => 'Trial workspace provisioned automatically.',
                    'actor_id' => $actor?->id,
                ];

                $followUps = $lead->follow_up_logs ?? [];
                $followUps[] = $logEntry;

                $lead->forceFill([
                    'status' => 'converted',
                    'stage' => 'converted',
                    'processed_at' => $lead->processed_at ?? now(),
                    'processed_by' => $actor?->id ?? $lead->processed_by,
                    'provisioned_store_id' => $store->id,
                    'provisioned_user_id' => $owner->id,
                    'provisioned_at' => now(),
                    'onboarding_url' => url('/owner'),
                    'follow_up_logs' => $followUps,
                ])->save();
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Failed to provision trial workspace: ' . $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'store' => $store,
            'owner' => $owner,
            'subscription' => $subscription,
            'temporary_password' => $temporaryPassword,
        ];
    }

    private function resolvePlan(?string $requestedSlug): ?Plan
    {
        if ($requestedSlug) {
            $plan = Plan::query()->active()->where('slug', $requestedSlug)->first();
            if ($plan) {
                return $plan;
            }
        }

        return Plan::query()->active()->ordered()->first();
    }

    private function createStore(LandingSubscription $lead): Store
    {
        $name = $lead->company
            ?: ($lead->name ? $lead->name . "'s Store" : 'Xpress POS Store');

        return Store::create([
            'name' => $name,
            'email' => $this->uniqueEmail($lead->email, 'stores'),
            'phone' => $lead->phone,
            'address' => Arr::get($lead->meta, 'address'),
            'settings' => [
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'tax_settings' => [
                    'tax_rate' => 0,           // Default 0, owner can configure
                    'tax_inclusive' => false,   // Tax separate from price
                    'tax_per_item' => false,   // Tax calculated at order level
                ],
                'service_charge_rate' => 0,
                'source' => 'landing',
            ],
            'status' => 'active',
        ]);
    }

    private function createOwner(LandingSubscription $lead, Store $store, string $temporaryPassword): User
    {
        $owner = User::create([
            'name' => $lead->name ?: $store->name . ' Owner',
            'email' => $this->uniqueEmail($lead->email, 'users'),
            'password' => Hash::make($temporaryPassword),
            'store_id' => $store->id,
        ]);

        $owner->forceFill(['email_verified_at' => now()])->save();
        $owner->assignRole('owner');

        return $owner;
    }

    private function createSubscription(LandingSubscription $lead, Store $store, Plan $plan): Subscription
    {
        $now = CarbonImmutable::now();

        return Subscription::create([
            'store_id' => $store->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => $now->toDateString(),
            'ends_at' => $now->addMonth()->toDateString(),
            'trial_ends_at' => $now->addDays(14)->toDateString(),
            'amount' => $plan->price ?? 0,
            'metadata' => [
                'source' => 'landing_subscription',
                'landing_subscription_id' => $lead->id,
            ],
        ]);
    }

    private function initialiseUsageRecords(Subscription $subscription, Plan $plan): void
    {
        $limits = $plan->limits ?? [];
        $transactionsLimit = $limits['transactions'] ?? null;

        if ($transactionsLimit !== null) {
            SubscriptionUsage::create([
                'subscription_id' => $subscription->id,
                'feature_type' => 'transactions',
                'current_usage' => 0,
                'annual_quota' => $transactionsLimit,
                'subscription_year_start' => now()->toDateString(),
                'subscription_year_end' => now()->addYear()->toDateString(),
                'soft_cap_triggered' => false,
            ]);
        }
    }

    private function uniqueEmail(string $email, string $table): string
    {
        $parts = Str::of($email);
        $local = Str::before($email, '@');
        $domain = Str::after($email, '@');

        $candidate = $email;
        $counter = 1;

        while (DB::table($table)->where('email', $candidate)->exists()) {
            $candidate = sprintf('%s+trial%d@%s', $local, $counter, $domain);
            $counter++;
        }

        return $candidate;
    }
}
