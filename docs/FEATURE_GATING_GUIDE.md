# Feature Gating System - Implementation Guide

## 📋 Overview

Sistem feature gating yang sudah diimplementasikan memungkinkan Anda untuk:
- Membatasi akses fitur berdasarkan subscription tier
- Enforce limits (products, staff, stores)
- Menampilkan upgrade prompts untuk free users
- Conditional rendering di Filament resources

## 🎯 Subscription Tiers

### Free Plan (Default)
```php
'features' => []
'limits' => [
    'stores' => 1,
    'products' => 50,
    'staff' => 2,
    'orders_per_month' => 100,
]
```

### Basic Plan (Rp 199,000/month)
```php
'features' => [
    'basic_reports',
    'inventory_management',
    'customer_management',
]
'limits' => [
    'stores' => 1,
    'products' => 500,
    'staff' => 10,
    'orders_per_month' => 1000,
]
```

### Pro Plan (Rp 499,000/month)
```php
'features' => [
    'basic_reports',
    'inventory_management',
    'customer_management',
    'advanced_analytics',
    'multi_store',
    'api_access',
    'custom_reports',
]
'limits' => [
    'stores' => 3,
    'products' => 2000,
    'staff' => 50,
    'orders_per_month' => -1, // unlimited
]
```

### Enterprise Plan (Custom pricing)
```php
'features' => [
    // All Pro features +
    'priority_support',
    'custom_integrations',
    'white_label',
]
'limits' => [
    'stores' => -1, // unlimited
    'products' => -1,
    'staff' => -1,
    'orders_per_month' => -1,
]
```

## 🔧 Usage Examples

### 1. Check Feature Access

```php
// In Controller
if (!auth()->user()->hasFeature('advanced_analytics')) {
    return redirect()->back()->with('error', 'Upgrade to Pro to access this feature');
}

// In Blade
@if(auth()->user()->hasFeature('advanced_analytics'))
    <x-advanced-analytics-dashboard />
@else
    <div class="upgrade-prompt">
        <p>Upgrade to Pro to unlock Advanced Analytics</p>
        <a href="{{ route('landing.pricing') }}">View Plans</a>
    </div>
@endif
```

### 2. Check Limits

```php
// Before creating a product
if (!auth()->user()->canCreate('products')) {
    return back()->with('error', 'Product limit reached. Please upgrade your plan.');
}

// Get current usage
$usage = auth()->user()->getUsagePercentage('products'); // Returns 0-100

// Get limit value
$limit = auth()->user()->getLimit('products'); // Returns integer
```

### 3. Protect Routes with Middleware

```php
// In routes/web.php
Route::middleware(['auth', 'subscription.feature:advanced_analytics'])
    ->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index']);
        Route::get('/reports/custom', [ReportController::class, 'custom']);
    });
```

### 4. Filament Resource Conditional Access

```php
// In Filament Resource
use Filament\Resources\Resource;

class AdvancedAnalyticsResource extends Resource
{
    public static function canViewAny(): bool
    {
        return auth()->user()->hasFeature('advanced_analytics');
    }
    
    public static function canCreate(): bool
    {
        return auth()->user()->canCreate('products');
    }
    
    // Show badge on navigation
    public static function getNavigationBadge(): ?string
    {
        if (!auth()->user()->hasFeature('advanced_analytics')) {
            return 'Pro';
        }
        return null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
```

### 5. Widget Conditional Visibility

```php
// In Filament Widget
class AdvancedAnalyticsWidget extends Widget
{
    public function isVisible(): bool
    {
        return auth()->user()->hasFeature('advanced_analytics');
    }
}

// For Free users, show upgrade banner instead
class UpgradeBannerWidget extends Widget
{
    public function isVisible(): bool
    {
        return auth()->user()->isFreePlan();
    }
}
```

## 📊 Available Helper Methods

### User Model Methods (via HasSubscriptionFeatures trait)

```php
// Feature checks
auth()->user()->hasFeature('advanced_analytics'); // bool
auth()->user()->hasActiveSubscription(); // bool
auth()->user()->isFreePlan(); // bool
auth()->user()->getSubscriptionTier(); // string: 'Free', 'Basic', 'Pro', 'Enterprise'

// Limit checks
auth()->user()->getLimit('products'); // int
auth()->user()->isWithinLimit('products', $currentCount); // bool
auth()->user()->canCreate('products'); // bool
auth()->user()->getUsagePercentage('products'); // int (0-100)

// Get current counts
auth()->user()->getCurrentCount('products'); // int
auth()->user()->getCurrentCount('staff'); // int
auth()->user()->getCurrentCount('stores'); // int
```

### SubscriptionFeatureService Methods

```php
$service = app(SubscriptionFeatureService::class);

// Check feature availability
$service->isFeatureAvailable($user, 'advanced_analytics'); // bool

// Get upgrade message
$service->getUpgradeMessage('advanced_analytics'); 
// Returns: "Upgrade to Pro to unlock Advanced Analytics"

// Get features comparison for pricing page
$service->getFeaturesComparison(); // array

// Check if upgrade needed
$service->needsUpgrade($user, 'create_store');
// Returns: ['needs_upgrade' => true, 'message' => '...', 'recommended_tier' => 'Pro']
```

## 🎨 UI Components

### Upgrade Banner Widget

Already created at `app/Filament/Owner/Widgets/UpgradeBannerWidget.php`

To use in Filament dashboard:
```php
// In OwnerPanelProvider.php
->widgets([
    \App\Filament\Owner\Widgets\UpgradeBannerWidget::class,
    // ... other widgets
])
```

### Custom Upgrade Prompt Component

```blade
{{-- resources/views/components/upgrade-prompt.blade.php --}}
<div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white">
    <h3 class="text-xl font-bold mb-2">🔒 {{ $title }}</h3>
    <p class="mb-4">{{ $message }}</p>
    <a href="{{ route('landing.pricing') }}" 
       class="inline-flex items-center px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-blue-50">
        Upgrade Now
    </a>
</div>
```

## 🚀 Next Steps

1. **Register UpgradeBannerWidget** in Filament panel
2. **Update existing resources** with `canViewAny()` checks
3. **Add navigation badges** for Pro features
4. **Test limits enforcement** when creating products/staff
5. **Add upgrade CTAs** in appropriate places

## 📝 Testing

```php
// Test feature access
$user = User::factory()->create();
$this->assertFalse($user->hasFeature('advanced_analytics'));
$this->assertTrue($user->isFreePlan());

// Test with subscription
$subscription = Subscription::factory()->create([
    'user_id' => $user->id,
    'plan_id' => Plan::where('slug', 'pro')->first()->id,
    'status' => 'active',
]);
$this->assertTrue($user->fresh()->hasFeature('advanced_analytics'));
```

## 🎯 Implementation Checklist

- [x] Trait `HasSubscriptionFeatures` created
- [x] Service `SubscriptionFeatureService` created
- [x] Middleware `CheckSubscriptionFeature` created
- [x] User model updated with trait
- [x] Middleware registered in bootstrap/app.php
- [x] Upgrade banner widget created
- [ ] Register widget in Filament panel
- [ ] Update resources with feature checks
- [ ] Add navigation badges
- [ ] Test all features

## 💡 Tips

1. **Always check limits before creating resources**
2. **Show upgrade prompts instead of hiding features completely** (better UX)
3. **Use navigation badges** to indicate Pro features
4. **Test with different subscription tiers**
5. **Cache subscription data** for better performance

---

**Created:** 2025-10-29
**Last Updated:** 2025-10-29
**Version:** 1.0.0
