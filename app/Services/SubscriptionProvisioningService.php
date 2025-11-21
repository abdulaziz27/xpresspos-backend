<?php

namespace App\Services;

use App\Models\LandingSubscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\SubscriptionUsage;
use App\Models\StoreUserAssignment;
use App\Enums\AssignmentRoleEnum;
use App\Mail\WelcomeNewOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Service untuk provisioning tenant, store, user, dan subscription dari landing subscription yang sudah dibayar.
 * 
 * Model Bisnis:
 * - Subscription per Tenant (bukan per Store)
 * - Satu tenant bisa punya banyak store, semua dilindungi oleh satu subscription yang sama
 */
class SubscriptionProvisioningService
{
    /**
     * Provision tenant, store, user, dan subscription dari landing subscription yang sudah dibayar.
     * 
     * @param LandingSubscription $landingSubscription
     * @param SubscriptionPayment $payment
     * @return array{success: bool, tenant?: Tenant, user?: User, store?: Store, subscription?: Subscription, temporary_password?: string, error?: string}
     */
    public function provisionFromPaidLandingSubscription(
        LandingSubscription $landingSubscription,
        SubscriptionPayment $payment
    ): array {
        try {
            return DB::transaction(function () use ($landingSubscription, $payment) {
                // 0. Idempotent guard - cek apakah sudah pernah di-provision
                if ($landingSubscription->subscription_id) {
                    Log::info('Landing subscription already provisioned', [
                        'landing_subscription_id' => $landingSubscription->id,
                        'subscription_id' => $landingSubscription->subscription_id,
                    ]);

                    $subscription = Subscription::find($landingSubscription->subscription_id);
                    if ($subscription) {
                        return [
                            'success' => true,
                            'tenant' => $subscription->tenant,
                            'user' => $landingSubscription->provisionedUser,
                            'store' => $landingSubscription->provisionedStore,
                            'subscription' => $subscription,
                            'message' => 'Already provisioned',
                        ];
                    }
                }

                // Validasi payment status
                if (!$payment->isPaid()) {
                    throw new \RuntimeException("Payment is not paid. Current status: {$payment->status}");
                }

                // Validasi plan
                $plan = Plan::find($landingSubscription->plan_id);
                if (!$plan) {
                    throw new \RuntimeException("Plan not found. Plan ID: {$landingSubscription->plan_id}");
                }

                // Cek apakah ini authenticated flow (user_id & tenant_id sudah ada)
                $isAuthenticatedFlow = $landingSubscription->user_id && $landingSubscription->tenant_id;

                if ($isAuthenticatedFlow) {
                    // Authenticated flow: tenant & user sudah ada, skip create
                    $tenant = Tenant::findOrFail($landingSubscription->tenant_id);
                    $user = User::findOrFail($landingSubscription->user_id);
                    $isNewUser = false;
                    $temporaryPassword = null;

                    Log::info('Provisioning for authenticated user', [
                        'landing_subscription_id' => $landingSubscription->id,
                        'user_id' => $user->id,
                        'tenant_id' => $tenant->id,
                    ]);
                } else {
                    // Anonymous flow (legacy): create tenant & user
                    // 1. Buat atau dapatkan Tenant
                    $tenant = $this->createOrGetTenant($landingSubscription);

                    // 2. Buat atau dapatkan User (owner)
                    $userData = $this->createOrGetUser($landingSubscription, $tenant);
                    $user = $userData['user'];
                    $isNewUser = $userData['isNewUser'];
                    $temporaryPassword = $userData['temporaryPassword'];

                    // Update landing_subscription dengan user_id & tenant_id (untuk konsistensi)
                    $landingSubscription->update([
                        'user_id' => $user->id,
                        'tenant_id' => $tenant->id,
                    ]);
                }

                // 3. Buat user_tenant_access (owner role)
                $this->ensureUserTenantAccess($user, $tenant);

                // 4. Buat Store pertama (link ke tenant_id) - hanya jika belum ada
                $store = $this->createStoreIfNeeded($landingSubscription, $tenant, $user);

                // 5. Buat store_user_assignments (owner, is_primary = true)
                $this->createStoreUserAssignment($store, $user);

                // 6. Buat Subscription (PER TENANT, pakai tenant_id)
                $subscription = $this->createSubscription($landingSubscription, $tenant, $plan, $payment);

                // 7. Buat SubscriptionUsage dari plan_features (optional tapi bagus)
                $this->createSubscriptionUsageFromPlan($subscription, $plan);

                // 8. Update landing_subscriptions & subscription_payments
                $this->updateLandingSubscription($landingSubscription, $tenant, $user, $store, $subscription);
                $this->updateSubscriptionPayment($payment, $subscription);

                // 9. Kirim email welcome + temp password (jika user baru)
                if ($isNewUser && $temporaryPassword) {
                    // Set temporary password di user object untuk email (non-persistent)
                    $user->temporary_password = $temporaryPassword;
                    $this->sendWelcomeEmail($user, $landingSubscription, $subscription);
                }

                Log::info('Provisioning completed successfully', [
                    'landing_subscription_id' => $landingSubscription->id,
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'store_id' => $store->id,
                    'subscription_id' => $subscription->id,
                ]);

            return [
                'success' => true,
                'tenant' => $tenant,
                'user' => $user,
                'store' => $store,
                'subscription' => $subscription,
                'temporary_password' => $temporaryPassword,
                'login_url' => $this->generateLoginUrl(),
            ];
            });
        } catch (\Exception $e) {
            Log::error('Provisioning failed', [
                'landing_subscription_id' => $landingSubscription->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Buat atau dapatkan Tenant dari landing subscription.
     */
    protected function createOrGetTenant(LandingSubscription $landingSubscription): Tenant
    {
        // Cek apakah email sudah punya tenant (untuk existing customer)
        $existingUser = User::where('email', $landingSubscription->email)->first();
        
        if ($existingUser) {
            // Ambil tenant dari user_tenant_access
            $tenantAccess = DB::table('user_tenant_access')
                ->where('user_id', $existingUser->id)
                ->where('role', 'owner')
                ->first();

            if ($tenantAccess) {
                $tenant = Tenant::find($tenantAccess->tenant_id);
                if ($tenant) {
                    Log::info('Using existing tenant for existing user', [
                        'tenant_id' => $tenant->id,
                        'user_id' => $existingUser->id,
                    ]);
                    return $tenant;
                }
            }
        }

        // Buat tenant baru
        $tenantName = $landingSubscription->business_name 
            ?? $landingSubscription->company 
            ?? $landingSubscription->name . ' Business';

        $tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'name' => $tenantName,
            'email' => $landingSubscription->email,
            'phone' => $landingSubscription->phone,
            'status' => 'active',
            'settings' => [
                'source' => 'landing_page',
                'landing_subscription_id' => $landingSubscription->id,
                'provisioned_at' => now()->toISOString(),
            ],
        ]);

        Log::info('Tenant created', [
            'tenant_id' => $tenant->id,
            'name' => $tenant->name,
            'landing_subscription_id' => $landingSubscription->id,
        ]);

        return $tenant;
    }

    /**
     * Buat atau dapatkan User dari landing subscription.
     * 
     * @return array{user: User, isNewUser: bool, temporaryPassword: string|null}
     */
    protected function createOrGetUser(LandingSubscription $landingSubscription, Tenant $tenant): array
    {
        // Cek apakah user sudah ada
        $existingUser = User::where('email', $landingSubscription->email)->first();

        if ($existingUser) {
            // Update user info
            $existingUser->update([
                'name' => $landingSubscription->name ?? $existingUser->name,
                'phone' => $landingSubscription->phone ?? $existingUser->phone,
            ]);

            Log::info('Using existing user', [
                'user_id' => $existingUser->id,
                'email' => $existingUser->email,
            ]);

            return [
                'user' => $existingUser,
                'isNewUser' => false,
                'temporaryPassword' => null,
            ];
        }

        // Buat user baru dengan temporary password
        $temporaryPassword = Str::random(12);
        
        $user = User::create([
            'name' => $landingSubscription->name,
            'email' => $landingSubscription->email,
            'password' => Hash::make($temporaryPassword),
            'email_verified_at' => now(), // Auto-verify karena sudah bayar
            'store_id' => null, // Akan di-set setelah store dibuat
        ]);

        // Assign owner role (Spatie Permission)
        if (!$user->hasRole('owner')) {
        $user->assignRole('owner');
        }

        Log::info('User created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'landing_subscription_id' => $landingSubscription->id,
        ]);

        return [
            'user' => $user,
            'isNewUser' => true,
            'temporaryPassword' => $temporaryPassword,
        ];
    }

    /**
     * Pastikan user punya akses ke tenant dengan role owner.
     */
    protected function ensureUserTenantAccess(User $user, Tenant $tenant): void
    {
        // Cek apakah sudah ada akses
        $existingAccess = DB::table('user_tenant_access')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($existingAccess) {
            // Update role ke owner jika belum
            if ($existingAccess->role !== 'owner') {
                DB::table('user_tenant_access')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $tenant->id)
                    ->update(['role' => 'owner']);
            }
            return;
        }

        // Buat akses baru
        DB::table('user_tenant_access')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('User tenant access created', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);
    }

    /**
     * Buat Store pertama untuk tenant (hanya jika belum ada).
     */
    protected function createStoreIfNeeded(LandingSubscription $landingSubscription, Tenant $tenant, User $user): Store
    {
        // Cek apakah tenant sudah punya store
        $existingStore = Store::where('tenant_id', $tenant->id)->first();

        if ($existingStore) {
            Log::info('Tenant already has store, using existing', [
                'tenant_id' => $tenant->id,
                'store_id' => $existingStore->id,
            ]);
            return $existingStore;
        }

        // Buat store baru
        $storeName = $landingSubscription->business_name 
            ?? $landingSubscription->company 
            ?? $tenant->name . ' Store';

        // Generate unique code
        $baseCode = Str::slug($storeName);
        $code = $this->generateUniqueStoreCode($baseCode);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => $storeName,
            'code' => $code,
            'email' => $landingSubscription->email ?? $user->email,
            'phone' => $landingSubscription->phone ?? $tenant->phone,
            'address' => $landingSubscription->meta['address'] ?? null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'status' => 'active',
            'settings' => [
                'business_type' => $landingSubscription->business_type ?? 'retail',
                'provisioned_from' => $landingSubscription->isAuthenticated() ? 'dashboard' : 'landing_page',
                'landing_subscription_id' => $landingSubscription->id,
            ],
        ]);

        // Update user store_id (legacy, untuk backward compatibility)
        if (!$user->store_id) {
            $user->update(['store_id' => $store->id]);
        }

        Log::info('Store created', [
            'store_id' => $store->id,
            'tenant_id' => $tenant->id,
            'name' => $store->name,
            'landing_subscription_id' => $landingSubscription->id,
        ]);

        return $store;
    }

    /**
     * Generate unique store code.
     */
    protected function generateUniqueStoreCode(string $baseCode): string
    {
        $code = $baseCode;
        $counter = 1;
        
        while (Store::where('code', $code)->exists()) {
            $code = $baseCode . '-' . $counter;
            $counter++;
        }
        
        return $code;
    }

    /**
     * Buat store_user_assignments untuk owner.
     */
    protected function createStoreUserAssignment(Store $store, User $user): void
    {
        // Cek apakah sudah ada assignment
        $existingAssignment = StoreUserAssignment::where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->first();
            
        if ($existingAssignment) {
            // Update ke owner dan primary jika belum
            $existingAssignment->update([
                'assignment_role' => AssignmentRoleEnum::OWNER,
                'is_primary' => true,
            ]);
            return;
        }

        // Buat assignment baru
        StoreUserAssignment::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'assignment_role' => AssignmentRoleEnum::OWNER,
            'is_primary' => true,
        ]);

        Log::info('Store user assignment created', [
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role' => AssignmentRoleEnum::OWNER->value,
        ]);
    }

    /**
     * Buat atau update Subscription untuk tenant (handles new, renewal, upgrade, downgrade).
     */
    protected function createSubscription(
        LandingSubscription $landingSubscription,
        Tenant $tenant,
        Plan $plan,
        SubscriptionPayment $payment
    ): Subscription {
        // Cek apakah tenant sudah punya subscription aktif
        $existingSubscription = $tenant->activeSubscription();

        if (! $existingSubscription) {
            $existingSubscription = $tenant->subscriptions()
                ->latest()
                ->first();
        }
            
        if ($existingSubscription) {
            // Determine action type
            $actionType = 'renewal'; // default
            if ($landingSubscription->isUpgrade()) {
                $actionType = 'upgrade';
            } elseif ($landingSubscription->isDowngrade()) {
                $actionType = 'downgrade';
            }
            
            // Calculate new end date
            $billingCycle = $landingSubscription->billing_cycle ?? 'monthly';
            $endsAt = $billingCycle === 'annual' 
                ? now()->addYear() 
                : now()->addMonth();
                
            // Update existing subscription
            $existingSubscription->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'amount' => $payment->amount,
                'metadata' => array_merge($existingSubscription->metadata ?? [], [
                    'action_type' => $actionType,
                    'previous_plan_id' => $landingSubscription->previous_plan_id,
                    'landing_subscription_id' => $landingSubscription->id,
                    'payment_id' => $payment->id,
                    'updated_at' => now()->toISOString(),
                ]),
            ]);

            // Recreate subscription_usage if plan changed (upgrade/downgrade)
            if ($landingSubscription->isPlanChange()) {
                $this->recreateSubscriptionUsage($existingSubscription, $plan);
                
                Log::info("Subscription {$actionType} completed", [
                    'subscription_id' => $existingSubscription->id,
                    'tenant_id' => $tenant->id,
                    'previous_plan_id' => $landingSubscription->previous_plan_id,
                    'new_plan_id' => $plan->id,
                    'change_type' => $landingSubscription->getChangeType(),
                ]);
            } else {
                Log::info('Subscription renewed', [
                    'subscription_id' => $existingSubscription->id,
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                ]);
            }

            return $existingSubscription;
        }
        
        // Buat subscription baru (first time)
        $billingCycle = $landingSubscription->billing_cycle ?? 'monthly';
        $endsAt = $billingCycle === 'annual' 
            ? now()->addYear() 
            : now()->addMonth();

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, // Subscription per tenant, bukan per store
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'amount' => $payment->amount,
            'metadata' => [
                'source' => 'landing_page',
                'action_type' => 'new',
                'landing_subscription_id' => $landingSubscription->id,
                'payment_id' => $payment->id,
                'xendit_invoice_id' => $payment->xendit_invoice_id,
                'provisioned_at' => now()->toISOString(),
            ],
        ]);

        Log::info('Subscription created', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
        ]);

        return $subscription;
    }

    /**
     * Recreate SubscriptionUsage untuk plan change (upgrade/downgrade).
     * 
     * IMPORTANT: This deletes existing usage and creates new ones based on new plan.
     * Current usage data is preserved in metadata for audit.
     */
    protected function recreateSubscriptionUsage(Subscription $subscription, Plan $newPlan): void
    {
        // 1. Backup existing usage untuk audit trail
        $existingUsage = SubscriptionUsage::where('subscription_id', $subscription->id)->get();
        $usageBackup = $existingUsage->map(function ($usage) {
            return [
                'feature_type' => $usage->feature_type,
                'current_usage' => $usage->current_usage,
                'annual_quota' => $usage->annual_quota,
                'backed_up_at' => now()->toISOString(),
            ];
        })->toArray();

        // Save backup to subscription metadata
        $subscription->update([
            'metadata' => array_merge($subscription->metadata ?? [], [
                'usage_backup_before_change' => $usageBackup,
                'usage_recreated_at' => now()->toISOString(),
            ]),
        ]);

        // 2. Delete existing usage records
        SubscriptionUsage::where('subscription_id', $subscription->id)->delete();

        Log::info('Subscription usage deleted for plan change', [
            'subscription_id' => $subscription->id,
            'records_deleted' => $existingUsage->count(),
        ]);

        // 3. Recreate dari new plan features
        $this->createSubscriptionUsageFromPlan($subscription, $newPlan);
    }

    /**
     * Buat SubscriptionUsage dari plan_features untuk tracking usage.
     */
    protected function createSubscriptionUsageFromPlan(Subscription $subscription, Plan $plan): void
    {
        // Ambil semua plan_features yang MAX_* (numeric limits yang perlu di-track)
        $features = PlanFeature::where('plan_id', $plan->id)
            ->where('is_enabled', true)
            ->where('feature_code', 'LIKE', 'MAX_%')
            ->get();

        foreach ($features as $feature) {
            // Map feature_code ke feature_type
            $featureType = $this->mapFeatureCodeToType($feature->feature_code);
            
            if (!$featureType) {
                continue; // Skip jika tidak ada mapping
            }

            // Cek apakah sudah ada usage record
            $existingUsage = SubscriptionUsage::where('subscription_id', $subscription->id)
                ->where('feature_type', $featureType)
                ->first();

            if ($existingUsage) {
                continue; // Skip jika sudah ada
            }

            // Buat usage record
            SubscriptionUsage::create([
                'subscription_id' => $subscription->id,
                'feature_type' => $featureType,
                'current_usage' => 0,
                'annual_quota' => $feature->getNumericLimit(),
                'subscription_year_start' => $subscription->starts_at->startOfYear(),
                'subscription_year_end' => $subscription->starts_at->endOfYear(),
                'soft_cap_triggered' => false,
            ]);

            Log::info('Subscription usage created', [
                'subscription_id' => $subscription->id,
                'feature_type' => $featureType,
                'feature_code' => $feature->feature_code,
                'quota' => $feature->getNumericLimit(),
            ]);
        }
    }

    /**
     * Map feature_code ke feature_type untuk subscription_usage.
     */
    protected function mapFeatureCodeToType(string $featureCode): ?string
    {
        $mapping = [
            'MAX_TRANSACTIONS_PER_YEAR' => 'transactions',
            'MAX_ORDERS_PER_MONTH' => 'orders',
            // Tambahkan mapping lain sesuai kebutuhan
        ];

        return $mapping[$featureCode] ?? null;
    }

    /**
     * Update landing_subscriptions dengan data yang sudah di-provision.
     */
    protected function updateLandingSubscription(
        LandingSubscription $landingSubscription, 
        Tenant $tenant,
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
            'processed_at' => now(),
            'onboarding_url' => $this->generateOnboardingUrl($user, $store),
        ]);

        Log::info('Landing subscription updated', [
            'landing_subscription_id' => $landingSubscription->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Update subscription_payment dengan subscription_id.
     */
    protected function updateSubscriptionPayment(SubscriptionPayment $payment, Subscription $subscription): void
    {
        $payment->update([
            'subscription_id' => $subscription->id,
        ]);

        Log::info('Subscription payment updated', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Kirim email welcome + temporary password (jika user baru).
     */
    protected function sendWelcomeEmail(User $user, LandingSubscription $landingSubscription, Subscription $subscription): void
    {
        try {
            // Temporary password sudah di-set di user object sebelum method ini dipanggil
            if (isset($user->temporary_password) && $user->temporary_password) {
                // Kirim welcome email dengan temporary password
                Mail::to($user->email)->send(new WelcomeNewOwner($user, $landingSubscription));
                
                Log::info('Welcome email sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } else {
                // Kirim subscription confirmation untuk existing user
                // TODO: Buat mail class SubscriptionRenewalConfirmation jika diperlukan
                Log::info('Existing user - welcome email skipped', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            // Jangan throw error, email bukan critical path
        }
    }

    /**
     * Generate login URL untuk user.
     */
    protected function generateLoginUrl(): string
    {
        return config('app.owner_url', url('/owner'));
    }

    /**
     * Generate onboarding URL untuk user.
     */
    protected function generateOnboardingUrl(User $user, Store $store): string
    {
        $baseUrl = $this->generateLoginUrl();
        return $baseUrl . '/onboarding?store=' . $store->code . '&welcome=1';
    }
}
