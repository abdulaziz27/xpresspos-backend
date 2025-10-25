<?php

namespace App\Services;

use App\Models\LandingSubscription;
use App\Models\SubscriptionPayment;
use App\Models\Subscription;
use App\Models\Store;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionActivationService
{
    /**
     * Activate subscription after successful payment.
     */
    public function activateSubscription(SubscriptionPayment $subscriptionPayment): array
    {
        if (!$subscriptionPayment->isPaid()) {
            throw new \Exception('Cannot activate subscription for unpaid payment');
        }

        $landingSubscription = $subscriptionPayment->landingSubscription;
        
        if (!$landingSubscription) {
            throw new \Exception('Landing subscription not found');
        }

        // Check if already activated
        if ($landingSubscription->provisioned_store_id && $landingSubscription->provisioned_user_id) {
            Log::info('Subscription already activated', [
                'landing_subscription_id' => $landingSubscription->id,
                'store_id' => $landingSubscription->provisioned_store_id,
                'user_id' => $landingSubscription->provisioned_user_id,
            ]);

            return [
                'store' => Store::find($landingSubscription->provisioned_store_id),
                'user' => User::find($landingSubscription->provisioned_user_id),
                'subscription' => Subscription::find($landingSubscription->subscription_id),
                'already_activated' => true,
            ];
        }

        return DB::transaction(function () use ($subscriptionPayment, $landingSubscription) {
            // 1. Create User account
            $user = $this->createUserAccount($landingSubscription);
            
            // 2. Create Store
            $store = $this->createStore($landingSubscription, $user);
            
            // 3. Create Subscription
            $subscription = $this->createSubscription($landingSubscription, $store, $subscriptionPayment);
            
            // 4. Update landing subscription with provisioned data
            $this->updateLandingSubscription($landingSubscription, $store, $user, $subscription);
            
            // 5. Update subscription payment with subscription link
            $subscriptionPayment->update(['subscription_id' => $subscription->id]);
            
            Log::info('Subscription activated successfully', [
                'landing_subscription_id' => $landingSubscription->id,
                'store_id' => $store->id,
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            return [
                'store' => $store,
                'user' => $user,
                'subscription' => $subscription,
                'already_activated' => false,
            ];
        });
    }

    /**
     * Create user account from landing subscription.
     */
    protected function createUserAccount(LandingSubscription $landingSubscription): User
    {
        // Generate temporary password
        $temporaryPassword = Str::random(12);
        
        $user = User::create([
            'name' => $landingSubscription->name,
            'email' => $landingSubscription->email,
            'password' => Hash::make($temporaryPassword),
            'email_verified_at' => now(), // Auto-verify since they paid
            'phone' => $landingSubscription->phone,
            'country' => $landingSubscription->country,
        ]);

        // Assign owner role
        $user->assignRole('owner');

        Log::info('User account created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'landing_subscription_id' => $landingSubscription->id,
        ]);

        return $user;
    }

    /**
     * Create store from landing subscription.
     */
    protected function createStore(LandingSubscription $landingSubscription, User $user): Store
    {
        $storeName = $landingSubscription->company ?: "{$landingSubscription->name}'s Store";
        
        $store = Store::create([
            'name' => $storeName,
            'slug' => Str::slug($storeName) . '-' . Str::random(6),
            'owner_id' => $user->id,
            'email' => $landingSubscription->email,
            'phone' => $landingSubscription->phone,
            'country' => $landingSubscription->country,
            'status' => 'active',
            'settings' => [
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'language' => 'id',
            ],
        ]);

        Log::info('Store created', [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'owner_id' => $user->id,
            'landing_subscription_id' => $landingSubscription->id,
        ]);

        return $store;
    }

    /**
     * Create subscription from landing subscription and payment.
     */
    protected function createSubscription(
        LandingSubscription $landingSubscription, 
        Store $store, 
        SubscriptionPayment $subscriptionPayment
    ): Subscription {
        // Get plan from landing subscription
        $plan = Plan::where('name', $landingSubscription->plan)->first();
        
        if (!$plan) {
            // Create default plan if not found
            $plan = Plan::create([
                'name' => $landingSubscription->plan ?: 'Basic',
                'price' => $subscriptionPayment->amount,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_products' => 100,
                    'max_orders_per_month' => 1000,
                    'max_staff' => 5,
                ],
                'is_active' => true,
            ]);
        }

        $subscription = Subscription::create([
            'store_id' => $store->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $plan->billing_cycle,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(), // Default 1 month
            'amount' => $subscriptionPayment->amount,
            'metadata' => [
                'activated_from_landing' => true,
                'landing_subscription_id' => $landingSubscription->id,
                'subscription_payment_id' => $subscriptionPayment->id,
            ],
        ]);

        Log::info('Subscription created', [
            'subscription_id' => $subscription->id,
            'store_id' => $store->id,
            'plan_id' => $plan->id,
            'amount' => $subscription->amount,
            'landing_subscription_id' => $landingSubscription->id,
        ]);

        return $subscription;
    }

    /**
     * Update landing subscription with provisioned data.
     */
    protected function updateLandingSubscription(
        LandingSubscription $landingSubscription,
        Store $store,
        User $user,
        Subscription $subscription
    ): void {
        $landingSubscription->update([
            'provisioned_store_id' => $store->id,
            'provisioned_user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'provisioned_at' => now(),
            'status' => 'activated',
            'stage' => 'subscription_active',
            'onboarding_url' => $this->generateOnboardingUrl($store, $user),
        ]);

        Log::info('Landing subscription updated with provisioned data', [
            'landing_subscription_id' => $landingSubscription->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Generate onboarding URL for the new user.
     */
    protected function generateOnboardingUrl(Store $store, User $user): string
    {
        // Generate secure token for onboarding
        $token = Str::random(64);
        
        // Store token in user meta or cache for verification
        $user->update([
            'remember_token' => $token, // Temporary use of remember_token for onboarding
        ]);

        return url("/onboarding/{$store->slug}?token={$token}&user={$user->id}");
    }

    /**
     * Check if subscription needs grace period handling.
     */
    public function handleGracePeriod(Subscription $subscription): void
    {
        if ($subscription->status !== 'active') {
            return;
        }

        $gracePeriodDays = config('xendit.grace_period_days', 3);
        $gracePeriodEnd = $subscription->ends_at->addDays($gracePeriodDays);

        if (now()->isAfter($gracePeriodEnd)) {
            $subscription->update(['status' => 'suspended']);
            
            Log::info('Subscription suspended after grace period', [
                'subscription_id' => $subscription->id,
                'grace_period_end' => $gracePeriodEnd,
            ]);
        }
    }

    /**
     * Reactivate suspended subscription after successful payment.
     */
    public function reactivateSubscription(Subscription $subscription, SubscriptionPayment $subscriptionPayment): void
    {
        if (!$subscriptionPayment->isPaid()) {
            throw new \Exception('Cannot reactivate subscription for unpaid payment');
        }

        $subscription->update([
            'status' => 'active',
            'ends_at' => now()->addMonth(), // Extend for another month
        ]);

        // Update subscription payment with subscription link
        $subscriptionPayment->update(['subscription_id' => $subscription->id]);

        Log::info('Subscription reactivated', [
            'subscription_id' => $subscription->id,
            'subscription_payment_id' => $subscriptionPayment->id,
            'new_end_date' => $subscription->ends_at,
        ]);
    }

    /**
     * Get activation status for a landing subscription.
     */
    public function getActivationStatus(LandingSubscription $landingSubscription): array
    {
        return [
            'is_activated' => !empty($landingSubscription->provisioned_store_id),
            'store_id' => $landingSubscription->provisioned_store_id,
            'user_id' => $landingSubscription->provisioned_user_id,
            'subscription_id' => $landingSubscription->subscription_id,
            'provisioned_at' => $landingSubscription->provisioned_at,
            'onboarding_url' => $landingSubscription->onboarding_url,
            'status' => $landingSubscription->status,
            'stage' => $landingSubscription->stage,
        ];
    }
}