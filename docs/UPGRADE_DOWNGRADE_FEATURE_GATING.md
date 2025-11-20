# Upgrade/Downgrade Flow & Feature Gating

## Overview

This document explains how the upgrade/downgrade flow works and how to implement feature gating in Filament panels to respect plan limits.

---

## Architecture

### 1. **Plan Hierarchy** (via `sort_order`)

```
sort_order:
1. Basic (sort_order = 1)
2. Pro (sort_order = 2)
3. Enterprise (sort_order = 3)
```

**Upgrade**: `new_plan.sort_order > current_plan.sort_order`  
**Downgrade**: `new_plan.sort_order < current_plan.sort_order`

---

### 2. **Database Schema Changes**

#### `landing_subscriptions` table:

```php
'is_upgrade' => boolean         // true if upgrading
'is_downgrade' => boolean       // true if downgrading
'previous_plan_id' => foreignId // ID of the plan being changed from
```

#### `subscriptions` table (unchanged):

- **No duplicate subscriptions** are created
- Existing subscription is **updated** with new `plan_id`
- `subscription_usage` is **recreated** based on new plan features

---

## Flow Diagram

### Upgrade/Downgrade Flow

```
User (authenticated) → Pricing Page → Select Plan
                                         |
                                         v
                        Detect Change Type (by sort_order)
                                         |
                    +--------------------|--------------------+
                    |                    |                    |
                Upgrade              Downgrade             Same Plan
                    |                    |                    |
                    v                    v                    |
             Button: "Upgrade"    Button: "Downgrade"     "Current Plan"
                    |                    |                 (disabled)
                    +--------------------|
                                         v
                             Checkout (auth required)
                                         |
                                         v
                    LandingSubscription::create([
                        'user_id' => ...,
                        'tenant_id' => ...,
                        'plan_id' => new_plan_id,
                        'is_upgrade' => true/false,
                        'is_downgrade' => true/false,
                        'previous_plan_id' => current_plan_id,
                    ])
                                         |
                                         v
                                 Payment (Xendit)
                                         |
                                         v
                                  Webhook Received
                                         |
                                         v
                        SubscriptionProvisioningService
                                         |
                    +--------------------|--------------------+
                    |                                         |
            if (isPlanChange())                        else: new subscription
                    |
                    v
        Update existing subscription:
        - plan_id = new_plan_id
        - amount = new_amount
        - billing_cycle = new_cycle
        - ends_at = calculate_new_end_date()
                    |
                    v
        Recreate subscription_usage:
        1. Backup existing usage to metadata
        2. Delete old usage records
        3. Create new usage from new plan_features
                    |
                    v
                 Complete ✅
```

---

## Implementation Guide

### 1. Pricing Page (`pricing.blade.php`)

Dynamic button labels based on current plan:

```php
@php
    $buttonLabel = 'Pilih Paket ' . $plan->name;
    $buttonClass = 'from-gray-600 to-gray-700';
    $buttonDisabled = false;
    
    if ($currentPlan) {
        if ($plan->id === $currentPlan->id) {
            $buttonLabel = 'Paket Saat Ini';
            $buttonDisabled = true;
            $buttonClass = 'from-gray-400 to-gray-500 cursor-not-allowed opacity-70';
        } elseif ($plan->sort_order > $currentPlan->sort_order) {
            $buttonLabel = 'Upgrade ke ' . $plan->name;
            $buttonClass = 'from-green-600 to-green-700';
        } elseif ($plan->sort_order < $currentPlan->sort_order) {
            $buttonLabel = 'Downgrade ke ' . $plan->name;
            $buttonClass = 'from-orange-600 to-orange-700';
        }
    }
@endphp
```

---

### 2. Checkout Controller (`LandingController`)

Detect and track upgrade/downgrade:

```php
// In processSubscription()
$activeSubscription = $tenant->activeSubscription();
$currentPlan = $activeSubscription?->plan;
$isUpgrade = false;
$isDowngrade = false;

if ($currentPlan) {
    if ($plan->sort_order > $currentPlan->sort_order) {
        $isUpgrade = true;
    } elseif ($plan->sort_order < $currentPlan->sort_order) {
        $isDowngrade = true;
    }
}

LandingSubscription::create([
    'user_id' => $user->id,
    'tenant_id' => $tenant->id,
    'plan_id' => $plan->id,
    'is_upgrade' => $isUpgrade,
    'is_downgrade' => $isDowngrade,
    'previous_plan_id' => $currentPlan?->id,
    // ... other fields
]);
```

---

### 3. Provisioning Service

Handle plan changes without creating duplicate subscriptions:

```php
protected function createSubscription(...): Subscription
{
    $existingSubscription = $tenant->activeSubscription();
    
    if ($existingSubscription) {
        // Update existing subscription (handles renewal, upgrade, downgrade)
        $existingSubscription->update([
            'plan_id' => $plan->id,
            'amount' => $payment->amount,
            // ... other fields
        ]);
        
        // Recreate usage if plan changed
        if ($landingSubscription->isPlanChange()) {
            $this->recreateSubscriptionUsage($existingSubscription, $plan);
        }
        
        return $existingSubscription;
    }
    
    // Create new subscription (first time)
    return Subscription::create([...]);
}
```

---

## Feature Gating in Filament

### Using `PlanLimitService`

The `PlanLimitService` provides methods to check plan features and limits:

```php
use App\Services\PlanLimitService;

$planLimitService = app(PlanLimitService::class);
$tenant = auth()->user()->currentTenant();

// Check if feature is allowed
if ($planLimitService->hasFeature($tenant, 'ALLOW_LOYALTY')) {
    // Show loyalty program menu
}

// Check if limit is exceeded
$storeCount = Store::where('tenant_id', $tenant->id)->count();
$maxStores = $planLimitService->getLimit($tenant, 'MAX_STORES');

if ($storeCount >= $maxStores) {
    // Disable "Create Store" button
}
```

---

### Example: Hide Menu Items Based on Plan

**File**: `app/Filament/Owner/Pages/Dashboard.php` (or any Filament page)

```php
use App\Services\PlanLimitService;

class Dashboard extends Page
{
    protected static string $view = 'filament.owner.pages.dashboard';
    
    public static function canAccess(): bool
    {
        // All users can access dashboard
        return true;
    }
    
    protected function getHeaderWidgets(): array
    {
        $planLimitService = app(PlanLimitService::class);
        $tenant = auth()->user()->currentTenant();
        
        $widgets = [
            // Always show
            \App\Filament\Owner\Widgets\StatsOverviewWidget::class,
        ];
        
        // Only show advanced widgets for Pro+ plans
        if ($planLimitService->hasFeature($tenant, 'ADVANCED_REPORTS')) {
            $widgets[] = \App\Filament\Owner\Widgets\AdvancedAnalyticsWidget::class;
        }
        
        return $widgets;
    }
}
```

---

### Example: Disable Create Button When Limit Reached

**File**: `app/Filament/Owner/Resources/StoreResource.php`

```php
use App\Services\PlanLimitService;
use Filament\Resources\Pages\CreateRecord;

class StoreResource extends Resource
{
    public static function canCreate(): bool
    {
        $planLimitService = app(PlanLimitService::class);
        $user = auth()->user();
        $tenant = $user->currentTenant();
        
        if (!$tenant) {
            return false;
        }
        
        // Check store limit
        $currentCount = Store::where('tenant_id', $tenant->id)->count();
        $maxStores = $planLimitService->getLimit($tenant, 'MAX_STORES');
        
        if ($currentCount >= $maxStores) {
            // Optionally, show a notification
            \Filament\Notifications\Notification::make()
                ->title('Store Limit Reached')
                ->body("You have reached the maximum number of stores ({$maxStores}) for your plan. Please upgrade to add more stores.")
                ->warning()
                ->send();
            
            return false;
        }
        
        return parent::canCreate();
    }
}
```

---

### Example: Hide Navigation Item

**File**: `app/Filament/Owner/Resources/LoyaltyProgramResource.php`

```php
use App\Services\PlanLimitService;

class LoyaltyProgramResource extends Resource
{
    // ... other methods
    
    public static function shouldRegisterNavigation(): bool
    {
        $planLimitService = app(PlanLimitService::class);
        $tenant = auth()->user()?->currentTenant();
        
        if (!$tenant) {
            return false;
        }
        
        // Only show for plans that have ALLOW_LOYALTY
        return $planLimitService->hasFeature($tenant, 'ALLOW_LOYALTY');
    }
}
```

---

## Testing Strategy

### 1. Automated Tests

```php
// tests/Feature/UpgradeDowngradeFlowTest.php

/** @test */
public function user_dapat_upgrade_dari_basic_ke_pro()
{
    // Setup: User dengan Basic plan
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $basicPlan = Plan::where('slug', 'basic')->first();
    $proPlan = Plan::where('slug', 'pro')->first();
    
    // Create active subscription
    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $basicPlan->id,
        'status' => 'active',
    ]);
    
    // Act: User checkout untuk upgrade
    $response = $this->actingAs($user)
        ->post(route('landing.subscription.process'), [
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'monthly',
        ]);
    
    // Assert: landing_subscription created dengan upgrade flag
    $this->assertDatabaseHas('landing_subscriptions', [
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'plan_id' => $proPlan->id,
        'is_upgrade' => true,
        'previous_plan_id' => $basicPlan->id,
    ]);
    
    // Simulate payment & provisioning
    $landingSubscription = LandingSubscription::latest()->first();
    $payment = SubscriptionPayment::factory()->paid()->create([
        'landing_subscription_id' => $landingSubscription->id,
    ]);
    
    $result = app(SubscriptionProvisioningService::class)
        ->provisionFromPaidLandingSubscription($landingSubscription, $payment);
    
    // Assert: subscription updated (not created new)
    $this->assertEquals(1, Subscription::where('tenant_id', $tenant->id)->count());
    
    $subscription->refresh();
    $this->assertEquals($proPlan->id, $subscription->plan_id);
}

/** @test */
public function subscription_usage_di_recreate_setelah_upgrade()
{
    // Test that subscription_usage is deleted and recreated
    // with new plan's features after upgrade
}

/** @test */
public function downgrade_tidak_menghilangkan_data_lama()
{
    // Test that downgrade preserves existing data
    // (stores, products, etc.) but hides features via gating
}
```

---

### 2. Manual Testing Checklist

#### **Upgrade Flow**:
1. ✅ Login sebagai user dengan Basic plan
2. ✅ Go to `/pricing`
3. ✅ Verify button labels: Basic = "Current Plan", Pro = "Upgrade", Enterprise = "Upgrade"
4. ✅ Click "Upgrade ke Pro"
5. ✅ Complete payment
6. ✅ Verify subscription plan_id updated to Pro
7. ✅ Verify subscription count = 1 (tidak ada duplicate)
8. ✅ Verify subscription_usage recreated dengan Pro features

#### **Downgrade Flow**:
1. ✅ Login sebagai user dengan Pro plan
2. ✅ Go to `/pricing`
3. ✅ Verify button labels: Basic = "Downgrade", Pro = "Current Plan"
4. ✅ Click "Downgrade ke Basic"
5. ✅ Complete payment
6. ✅ Verify subscription plan_id updated to Basic
7. ✅ Verify advanced features hidden in dashboard
8. ✅ Verify data tetap ada (tidak hilang)

#### **Feature Gating**:
1. ✅ Login dengan Basic plan
2. ✅ Verify "Loyalty Program" menu hidden
3. ✅ Verify "Advanced Reports" widget tidak tampil
4. ✅ Create 1 store (max Basic = 1)
5. ✅ Verify "Create Store" button disabled
6. ✅ Upgrade to Pro
7. ✅ Verify "Loyalty Program" menu now visible
8. ✅ Verify "Create Store" button enabled again

---

## Benefits

### ✅ **No Duplicate Subscriptions**
- One subscription per tenant
- Update in-place for upgrades/downgrades

### ✅ **Accurate Usage Tracking**
- subscription_usage recreated with new plan limits
- Old usage backed up to metadata for audit

### ✅ **Clean UX**
- Dynamic buttons based on current plan
- Clear upgrade/downgrade labeling

### ✅ **Data Safety**
- Downgrade doesn't delete data
- Features gated via `PlanLimitService`

### ✅ **Audit Trail**
- All changes tracked in landing_subscriptions
- previous_plan_id for history
- Metadata contains change type & timestamp

---

## Next Steps

1. ✅ Implement feature gating in all Filament resources
2. ✅ Add usage tracking in POS API
3. ✅ Create upgrade prompt when limits exceeded
4. ✅ Add plan comparison table in pricing page
5. ✅ Send email notification on successful upgrade/downgrade

---

**Status**: ✅ Core implementation complete  
**Last Updated**: 2025-11-19  
**Author**: AI Assistant

