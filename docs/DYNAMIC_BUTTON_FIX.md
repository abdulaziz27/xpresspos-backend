# Fix: Dynamic Pricing Buttons di Homepage (#pricing section)

## Problem
Tombol di homepage `http://127.0.0.1:8000/#pricing` tidak beradaptasi dengan current plan user.
Semua tombol masih menampilkan "Beli" meskipun user sudah login dan punya active subscription.

## Root Cause
1. Homepage (`/`) menggunakan view `landing.xpresspos` yang berbeda dari `/pricing` page
2. Controller `index()` tidak pass variable `$currentPlan` ke view
3. Button di pricing table hardcoded text "Beli"

## Solution

### 1. Update Controller `LandingController::index()`
**File**: `app/Http/Controllers/LandingController.php`

**Changes**:
```php
public function index()
{
    $plans = Plan::active()->ordered()->get();
    
    // Get current user's tenant and active plan (for dynamic pricing UI)
    $currentPlan = null;
    $tenant = null;
    
    if (Auth::check()) {
        $user = Auth::user();
        $tenant = $user->currentTenant();
        
        if ($tenant) {
            $activeSubscription = $tenant->activeSubscription();
            $currentPlan = $activeSubscription?->plan;
        }
    }
    
    return view('landing.xpresspos', [
        'title' => 'XpressPOS - AI Maksimalkan Bisnismu',
        'plans' => $plans,
        'currentPlan' => $currentPlan,  // ← ADDED
        'tenant' => $tenant,             // ← ADDED
    ]);
}
```

---

### 2. Update View `landing/xpresspos.blade.php`
**File**: `resources/views/landing/xpresspos.blade.php`

**Changes** (line ~410-437):
```php
@php
    // Dynamic button text based on auth & current plan
    $btnLabel = 'Beli';
    $btnDisabled = false;
    $btnOnclick = "selectPlanFromLanding('{$plan->slug}')";
    
    // Check if user has active plan
    if (isset($currentPlan) && $currentPlan) {
        if ($plan->id === $currentPlan->id) {
            // Current plan
            $btnLabel = 'Paket Saat Ini ✓';
            $btnDisabled = true;
            $btnOnclick = '';
        } elseif ($plan->sort_order > $currentPlan->sort_order) {
            // Upgrade
            $btnLabel = 'Upgrade';
        } elseif ($plan->sort_order < $currentPlan->sort_order) {
            // Downgrade
            $btnLabel = 'Downgrade';
        }
    }
@endphp
<button 
    @if($btnOnclick) onclick="{{ $btnOnclick }}" @endif
    @if($btnDisabled) disabled @endif
    class="mt-3 w-full {{ $index === 1 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-900 hover:bg-gray-800' }} text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors {{ $btnDisabled ? 'opacity-60 cursor-not-allowed' : '' }}">
    {{ $btnLabel }}
</button>
```

**Key Points**:
- ✅ **Warna tetap sama** (blue untuk popular, gray untuk lainnya)
- ✅ **Hanya text label yang berubah** ("Beli", "Upgrade", "Downgrade", "Paket Saat Ini ✓")
- ✅ **Button disabled untuk current plan** (opacity 60%, cursor not-allowed)

---

### 3. Update View `landing/pricing.blade.php` (for consistency)
**File**: `resources/views/landing/pricing.blade.php`

**Changes** (line ~86-124):
```php
@php
    // Default values
    $buttonLabel = 'Pilih Paket ' . $plan->name;
    $buttonDisabled = false;
    $buttonAction = true; // Can click
    
    // Default color: gray untuk basic, blue untuk popular (Pro)
    if ($index === 1) {
        $buttonClass = 'from-blue-600 to-blue-700'; // Popular plan
    } else {
        $buttonClass = 'from-gray-600 to-gray-700'; // Default
    }
    
    // Dynamic button ONLY for authenticated users with active plan
    if (isset($currentPlan) && $currentPlan) {
        if ($plan->id === $currentPlan->id) {
            // Current plan - TETAP GUNAKAN WARNA ASLI (blue/gray)
            $buttonLabel = 'Paket Saat Ini ✓';
            $buttonDisabled = true;
            $buttonAction = false;
            // Warna tetap sama, hanya tambah opacity
        } elseif ($plan->sort_order > $currentPlan->sort_order) {
            // Upgrade - TETAP GUNAKAN WARNA ASLI
            $buttonLabel = 'Upgrade ke ' . $plan->name;
            // Warna tetap dari default (blue/gray)
        } elseif ($plan->sort_order < $currentPlan->sort_order) {
            // Downgrade - TETAP GUNAKAN WARNA ASLI
            $buttonLabel = 'Downgrade ke ' . $plan->name;
            // Warna tetap dari default (blue/gray)
        }
    }
@endphp

<button 
    @if($buttonAction) onclick="selectPlan({{ $plan->id }}, '{{ $plan->slug }}')" @endif
    @if($buttonDisabled) disabled @endif
    class="w-full bg-gradient-to-r {{ $buttonClass }} text-white py-4 px-6 rounded-xl font-semibold transition-all duration-300 {{ $buttonDisabled ? 'opacity-60 cursor-not-allowed' : 'hover:shadow-xl transform hover:-translate-y-0.5 hover:scale-105' }}">
    {{ $buttonLabel }}
</button>
```

---

## Testing

### Manual Test Steps:

1. **Guest User** (not logged in):
   ```
   Visit: http://127.0.0.1:8000/#pricing
   Expected: All buttons show "Beli" with original colors
   ```

2. **User with Pro Plan** (e.g., `owner@xpresspos.id`):
   ```
   Login → Visit: http://127.0.0.1:8000/#pricing
   Expected:
   - Basic: "Downgrade" (gray button)
   - Pro: "Paket Saat Ini ✓" (blue button, disabled, opacity 60%)
   - Enterprise: "Upgrade" (gray button)
   ```

3. **User with Basic Plan**:
   ```
   Login → Visit: http://127.0.0.1:8000/#pricing
   Expected:
   - Basic: "Paket Saat Ini ✓" (gray button, disabled)
   - Pro: "Upgrade" (blue button)
   - Enterprise: "Upgrade" (gray button)
   ```

4. **Verify `/pricing` page** (standalone):
   ```
   Visit: http://127.0.0.1:8000/pricing
   Expected: Same behavior as homepage #pricing section
   ```

---

## Button States Summary

| User State | Plan Comparison | Button Text | Button Color | Disabled |
|-----------|----------------|-------------|--------------|----------|
| Guest | - | "Beli" | Original (blue/gray) | No |
| Authenticated (No Plan) | - | "Beli" | Original (blue/gray) | No |
| Authenticated (Has Plan) | Same Plan | "Paket Saat Ini ✓" | Original (blue/gray) | Yes (opacity 60%) |
| Authenticated (Has Plan) | Higher Tier | "Upgrade" | Original (blue/gray) | No |
| Authenticated (Has Plan) | Lower Tier | "Downgrade" | Original (blue/gray) | No |

**Key Design Decision**: 
- ✅ **Warna TIDAK berubah** (tetap blue untuk popular, gray untuk lainnya)
- ✅ **Hanya text label yang dinamis**
- ✅ **Consistency** across homepage dan pricing page

---

## Files Modified

1. `app/Http/Controllers/LandingController.php` - Added `$currentPlan` & `$tenant` to view data
2. `resources/views/landing/xpresspos.blade.php` - Dynamic button logic added
3. `resources/views/landing/pricing.blade.php` - Updated to maintain color consistency

---

## Result

✅ **FIXED**: Tombol di homepage `#pricing` sekarang beradaptasi dengan current plan user  
✅ **MAINTAINED**: Warna dan style button tetap sama seperti desain asli  
✅ **CONSISTENT**: Behavior sama antara homepage dan `/pricing` page

---

**Date**: 2025-11-19  
**Status**: ✅ Complete

