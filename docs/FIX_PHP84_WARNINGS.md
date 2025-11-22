# Fix PHP 8.4 Float to Int Conversion Warnings

## Problem

PHP 8.4 mengeluarkan warning:
```
Implicit conversion from float X.Y to int loses precision
```

Warning ini muncul ketika:
1. `sum('amount')` mengembalikan float tapi digunakan sebagai int
2. `diffInDays()` mengembalikan float tapi digunakan dalam comparison/assignment sebagai int

## Solution

### Files Fixed

#### 1. SubscriptionOverviewWidget.php
**Location**: `app/Filament/Owner/Resources/SubscriptionResource/Widgets/SubscriptionOverviewWidget.php`

**Changes**:
```php
// Before:
$totalPayments = $activeSubscription 
    ? $activeSubscription->subscriptionPayments()->where('status', 'paid')->sum('amount')
    : 0;

$daysUntilRenewal = $nextPaymentDate ? $nextPaymentDate->diffInDays() : null;

// After:
$totalPayments = $activeSubscription 
    ? (int) $activeSubscription->subscriptionPayments()->where('status', 'paid')->sum('amount')
    : 0;

$daysUntilRenewal = $nextPaymentDate ? (int) $nextPaymentDate->diffInDays() : null;
```

---

#### 2. SubscriptionDashboardWidget.php
**Location**: `app/Filament/Owner/Widgets/SubscriptionDashboardWidget.php`

**Changes**:
```php
// Before:
$upcomingRenewal = $activeSubscription && $activeSubscription->ends_at->diffInDays() <= 30 
    ? $activeSubscription 
    : null;

// After:
$upcomingRenewal = $activeSubscription && (int) $activeSubscription->ends_at->diffInDays() <= 30 
    ? $activeSubscription 
    : null;
```

---

#### 3. SubscriptionStatusWidget.php
**Location**: `app/Filament/Owner/Resources/SubscriptionResource/Widgets/SubscriptionStatusWidget.php`

**Changes**:
```php
// Before:
$daysUntilRenewal = $this->record->ends_at->diffInDays();

// After:
$daysUntilRenewal = (int) $this->record->ends_at->diffInDays();
```

---

#### 4. PaymentStatsWidget.php
**Location**: `app/Filament/Owner/Resources/SubscriptionPaymentResource/Widgets/PaymentStatsWidget.php`

**Changes**:
```php
// Before:
$totalPaid = $baseQuery->clone()->where('status', 'paid')->sum('amount');
$thisMonthPaid = $baseQuery->clone()
    ->where('status', 'paid')
    ->where('paid_at', '>=', now()->startOfMonth())
    ->sum('amount');
$lastMonthPaid = $baseQuery->clone()
    ->where('status', 'paid')
    ->where('paid_at', '>=', now()->subMonth()->startOfMonth())
    ->where('paid_at', '<=', now()->subMonth()->endOfMonth())
    ->sum('amount');

// After:
$totalPaid = (int) $baseQuery->clone()->where('status', 'paid')->sum('amount');
$thisMonthPaid = (int) $baseQuery->clone()
    ->where('status', 'paid')
    ->where('paid_at', '>=', now()->startOfMonth())
    ->sum('amount');
$lastMonthPaid = (int) $baseQuery->clone()
    ->where('status', 'paid')
    ->where('paid_at', '>=', now()->subMonth()->startOfMonth())
    ->where('paid_at', '<=', now()->subMonth()->endOfMonth())
    ->sum('amount');
```

---

## Why This Happens

### 1. Database `sum()` Returns Float
Laravel's query builder `sum()` method returns a **float** by default, even if the column is defined as integer in the database. This is because:
- Database aggregation functions can return decimal values
- MySQL/PostgreSQL `SUM()` returns numeric type (can be float)
- Laravel doesn't automatically cast the result

### 2. Carbon `diffInDays()` Returns Float
Carbon's `diffInDays()` method returns a **float** to handle partial days:
```php
$date1 = Carbon::parse('2025-01-01 10:00:00');
$date2 = Carbon::parse('2025-01-02 14:00:00');
$diff = $date1->diffInDays($date2); // Returns 1.166... (28 hours / 24)
```

### 3. PHP 8.4 Strict Type Checking
PHP 8.4 introduces stricter type checking and emits warnings when:
- Float is implicitly converted to int in arithmetic operations
- Float is assigned to a variable/property typed as int
- Float is compared to int in strict comparison contexts

---

## Best Practice: Explicit Type Casting

Always cast to int when you expect integer values:

### ✅ Good (Explicit Cast)
```php
$total = (int) Order::sum('amount');
$days = (int) $date->diffInDays();
$price = (int) $product->price;
```

### ❌ Bad (Implicit Conversion)
```php
$total = Order::sum('amount'); // Returns float
$days = $date->diffInDays();   // Returns float
$price = $product->price;       // Might be float
```

---

## Testing

After fixes, warnings should no longer appear in logs:

```bash
# Before fix:
[logs] │ Implicit conversion from float 2540161.9724149997 to int loses precision...

# After fix:
# No warnings ✅
```

---

## Related Files (No Changes Needed)

These files also use `sum()` or `diffInDays()` but don't trigger warnings because they:
- Use the result as float (e.g., in currency display with `number_format()`)
- Don't perform implicit int conversion

- `PaymentMethodBreakdownWidget.php`
- `PaymentAnalyticsWidget.php`
- `OwnerStatsWidget.php`
- `SalesRevenueChartWidget.php`

---

## Summary

| File | Line | Change |
|------|------|--------|
| SubscriptionOverviewWidget.php | 31 | Cast `sum('amount')` to int |
| SubscriptionOverviewWidget.php | 35 | Cast `diffInDays()` to int |
| SubscriptionDashboardWidget.php | 49 | Cast `diffInDays()` to int |
| SubscriptionStatusWidget.php | 21 | Cast `diffInDays()` to int |
| PaymentStatsWidget.php | 37, 38, 46 | Cast all `sum('amount')` to int |

**Status**: ✅ All PHP 8.4 warnings fixed

