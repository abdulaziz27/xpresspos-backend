<?php

namespace App\Services;

use App\Models\LandingSubscription;
use App\Models\User;
use App\Models\Store;
use App\Models\Subscription;
use App\Mail\WelcomeNewOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SubscriptionProvisioningService
{
    /**
     * Provision a new user account and store after successful payment.
     */
    public function provisionSubscription(LandingSubscription $landingSubscription): array
    {
        try {
            DB::beginTransaction();

            // 1. Create User Account
            $user = $this->createUserAccount($landingSubscription);
            
            // 2. Create Store
            $store = $this->createStore($landingSubscription, $user);
            
            // 3. Create Subscription Record
            $subscription = $this->createSubscription($landingSubscription, $user, $store);
            
            // 4. Update Landing Subscription
            $this->updateLandingSubscription($landingSubscription, $user, $store, $subscription);
            
            // 5. Send Welcome Email
            $this->sendWelcomeEmail($user, $landingSubscription);

            DB::commit();

            return [
                'success' => true,
                'user' => $user,
                'store' => $store,
                'subscription' => $subscription,
                'login_url' => $this->generateLoginUrl(),
                'temporary_password' => $user->temporary_password ?? null
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Subscription provisioning failed', [
                'landing_subscription_id' => $landingSubscription->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create or find existing user account from landing subscription data.
     */
    private function createUserAccount(LandingSubscription $landingSubscription): User
    {
        // Check if user already exists
        $existingUser = User::where('email', $landingSubscription->email)->first();
        
        if ($existingUser) {
            // User exists - update their information and reactivate if needed
            $existingUser->update([
                'name' => $landingSubscription->name,
                'phone' => $landingSubscription->phone,
                'is_active' => true,
                'email_verified_at' => $existingUser->email_verified_at ?? now(),
            ]);

            // Ensure they have owner role
            if (!$existingUser->hasRole('owner')) {
                $existingUser->assignRole('owner');
            }

            // No temporary password for existing users
            $existingUser->temporary_password = null;
            
            \Log::info('Existing user reactivated for subscription', [
                'user_id' => $existingUser->id,
                'email' => $existingUser->email,
                'landing_subscription_id' => $landingSubscription->id
            ]);

            return $existingUser;
        }

        // Create new user
        $temporaryPassword = Str::random(12);
        
        $user = User::create([
            'name' => $landingSubscription->name,
            'email' => $landingSubscription->email,
            'password' => Hash::make($temporaryPassword),
            'email_verified_at' => now(), // Auto-verify since they paid
            'phone' => $landingSubscription->phone,
            'is_active' => true,
        ]);

        // Store temporary password for email (will be cleared after first login)
        $user->temporary_password = $temporaryPassword;
        $user->save();

        // Assign owner role
        $user->assignRole('owner');

        \Log::info('New user created for subscription', [
            'user_id' => $user->id,
            'email' => $user->email,
            'landing_subscription_id' => $landingSubscription->id
        ]);

        return $user;
    }

    /**
     * Create store for the business.
     */
    private function createStore(LandingSubscription $landingSubscription, User $user): Store
    {
        $businessType = $this->getBusinessTypeFromMeta($landingSubscription);
        $businessName = $landingSubscription->company ?? $landingSubscription->business_name;
        
        // Generate unique slug for the store
        $baseSlug = Str::slug($businessName);
        $slug = $this->generateUniqueStoreSlug($baseSlug, $user->id);
        
        $store = Store::create([
            'name' => $businessName,
            'slug' => $slug,
            'description' => "Toko {$landingSubscription->name} - {$businessType}",
            'owner_id' => $user->id,
            'business_type' => $businessType,
            'phone' => $landingSubscription->phone,
            'email' => $landingSubscription->email,
            'is_active' => true,
            'settings' => [
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'language' => 'id',
                'plan' => $landingSubscription->plan ?? $landingSubscription->plan_id,
                'billing_cycle' => $this->getBillingCycleFromMeta($landingSubscription),
            ]
        ]);

        \Log::info('Store created for subscription', [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'user_id' => $user->id,
            'landing_subscription_id' => $landingSubscription->id
        ]);

        return $store;
    }

    /**
     * Generate unique store slug to avoid conflicts.
     */
    private function generateUniqueStoreSlug(string $baseSlug, int $userId): string
    {
        $slug = $baseSlug;
        $counter = 1;
        
        // Check if slug exists for this user or globally
        while (Store::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Create subscription record.
     */
    private function createSubscription(LandingSubscription $landingSubscription, User $user, Store $store): Subscription
    {
        $planId = $landingSubscription->plan ?? $landingSubscription->plan_id;
        $billingCycle = $this->getBillingCycleFromMeta($landingSubscription);
        
        // Check for existing active subscription for this store
        $existingSubscription = Subscription::where('store_id', $store->id)
            ->where('status', 'active')
            ->first();
            
        if ($existingSubscription) {
            // Update existing subscription instead of creating new one
            $nextBillingDate = $billingCycle === 'yearly' 
                ? now()->addYear() 
                : now()->addMonth();
                
            $existingSubscription->update([
                'plan_id' => $planId,
                'billing_cycle' => $billingCycle,
                'amount' => $landingSubscription->payment_amount,
                'next_billing_date' => $nextBillingDate,
                'status' => 'active',
                'metadata' => array_merge($existingSubscription->metadata ?? [], [
                    'renewed_from_landing' => true,
                    'landing_subscription_id' => $landingSubscription->id,
                    'xendit_invoice_id' => $landingSubscription->xendit_invoice_id,
                    'renewed_at' => now()->toISOString(),
                ])
            ]);

            \Log::info('Existing subscription renewed', [
                'subscription_id' => $existingSubscription->id,
                'store_id' => $store->id,
                'user_id' => $user->id,
                'landing_subscription_id' => $landingSubscription->id
            ]);

            return $existingSubscription;
        }
        
        // Calculate next billing date for new subscription
        $nextBillingDate = $billingCycle === 'yearly' 
            ? now()->addYear() 
            : now()->addMonth();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'plan_id' => $planId,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'amount' => $landingSubscription->payment_amount,
            'currency' => 'IDR',
            'started_at' => now(),
            'next_billing_date' => $nextBillingDate,
            'trial_ends_at' => null, // No trial since they paid
            'metadata' => [
                'source' => 'landing_page',
                'landing_subscription_id' => $landingSubscription->id,
                'xendit_invoice_id' => $landingSubscription->xendit_invoice_id,
            ]
        ]);

        \Log::info('New subscription created', [
            'subscription_id' => $subscription->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'landing_subscription_id' => $landingSubscription->id
        ]);

        return $subscription;
    }

    /**
     * Update landing subscription with provisioned data.
     */
    private function updateLandingSubscription(
        LandingSubscription $landingSubscription, 
        User $user, 
        Store $store, 
        Subscription $subscription
    ): void {
        $landingSubscription->update([
            'status' => 'provisioned',
            'stage' => 'active',
            'provisioned_user_id' => $user->id,
            'provisioned_store_id' => $store->id,
            'subscription_id' => $subscription->id,
            'provisioned_at' => now(),
            'onboarding_url' => $this->generateOnboardingUrl($user, $store),
        ]);
    }

    /**
     * Send appropriate email based on user status.
     */
    private function sendWelcomeEmail(User $user, LandingSubscription $landingSubscription): void
    {
        try {
            // Determine if this is a new user or existing user
            $isNewUser = $user->temporary_password !== null;
            
            if ($isNewUser) {
                // Send welcome email with login credentials for new users
                Mail::to($user->email)->send(new WelcomeNewOwner($user, $landingSubscription));
            } else {
                // Send subscription confirmation email for existing users
                Mail::to($user->email)->send(new \App\Mail\SubscriptionRenewalConfirmation($user, $landingSubscription));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send welcome/renewal email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'is_new_user' => $isNewUser ?? false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get business type from meta data.
     */
    private function getBusinessTypeFromMeta(LandingSubscription $landingSubscription): string
    {
        $meta = $landingSubscription->meta ?? [];
        return $meta['business_type'] ?? 'retail';
    }

    /**
     * Get billing cycle from meta data.
     */
    private function getBillingCycleFromMeta(LandingSubscription $landingSubscription): string
    {
        $meta = $landingSubscription->meta ?? [];
        return $meta['billing_cycle'] ?? $landingSubscription->billing_cycle ?? 'monthly';
    }

    /**
     * Generate login URL for the user.
     */
    private function generateLoginUrl(): string
    {
        if (app()->environment('local')) {
            return url('/owner-panel');
        }
        
        return config('domains.owner', 'https://owner.xpresspos.id');
    }

    /**
     * Generate onboarding URL for the user.
     */
    private function generateOnboardingUrl(User $user, Store $store): string
    {
        $baseUrl = $this->generateLoginUrl();
        return $baseUrl . '/onboarding?store=' . $store->slug . '&welcome=1';
    }
}