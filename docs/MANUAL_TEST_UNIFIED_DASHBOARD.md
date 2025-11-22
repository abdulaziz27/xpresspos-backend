# Manual Testing Guide: Unified Multi-Store Dashboard

## 🎯 Test Objective
Verify that the **Unified Multi-Store Dashboard** with **Global Filter** works correctly across different scenarios.

---

## ⚙️ Pre-Test Setup

### 1. Ensure Database Has Required Data
```bash
# Check if you have tenant, stores, and test data
php artisan tinker
```

```php
// In tinker:
\App\Models\Tenant::count();  // Should be > 0
\App\Models\Store::count();   // Should be > 1 (for multi-store testing)
\App\Models\Order::count();   // Should be > 0
\App\Models\Payment::count(); // Should be > 0
```

### 2. Create Test Data (if needed)
```bash
# Run seeders
php artisan db:seed --class=TenantSeeder
php artisan db:seed --class=StoreSeeder

# Or manually create via Filament
```

### 3. Clear Caches
```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan cache:clear
```

---

## 🧪 Test Cases

### TEST 1: Access Dashboard & See Global Filter
**Steps**:
1. Login to Filament Owner Panel: `http://127.0.0.1:8000/owner`
2. Navigate to Dashboard

**Expected Result**:
- ✅ Global Filter Widget tampil di bagian atas dashboard
- ✅ Filter terdiri dari:
  - **Cabang** (Store dropdown) dengan opsi "🏢 Semua Cabang" + individual stores
  - **Periode** (Date preset dropdown) dengan opsi: Hari Ini, Kemarin, Minggu Ini, Bulan Ini, dll.
  - **Tanggal Custom** (Date range picker, visible when preset = "Custom")
- ✅ Current filter summary tampil di bawah form (badges with icons)
- ✅ Pro Tip banner tampil

**Screenshot Checklist**:
- [ ] Widget visible
- [ ] Dropdowns populated
- [ ] Summary badges showing correct values

---

### TEST 2: Change Store Filter
**Steps**:
1. Di Global Filter, klik dropdown **Cabang**
2. Pilih **"Semua Cabang"**
3. Wait for page refresh
4. Observe widget values (Total Transaksi, Total Pendapatan, etc.)
5. Change to specific store (e.g., "Toko Pusat")
6. Wait for page refresh
7. Observe widget values again

**Expected Result**:
- ✅ When "Semua Cabang" selected:
  - Stats show **aggregated data** from all stores
  - Summary badge shows "Semua Cabang"
- ✅ When specific store selected:
  - Stats show **data for that store only**
  - Summary badge shows store name (e.g., "Toko Pusat")
- ✅ Values change appropriately
- ✅ No errors in console/terminal

**Data Validation**:
```bash
# Verify in tinker:
$storeIds = \App\Models\Store::where('tenant_id', 'YOUR_TENANT_ID')->pluck('id');

# All stores:
\App\Models\Order::whereIn('store_id', $storeIds)->count();

# Single store:
\App\Models\Order::where('store_id', 'SPECIFIC_STORE_ID')->count();
```

---

### TEST 3: Change Date Filter
**Steps**:
1. Di Global Filter, klik dropdown **Periode**
2. Pilih **"Hari Ini"**
3. Wait for refresh → Observe stats
4. Pilih **"Minggu Ini"**
5. Wait for refresh → Observe stats
6. Pilih **"Bulan Ini"**
7. Wait for refresh → Observe stats
8. Pilih **"Custom"**
9. Pick custom date range (e.g., last 7 days)
10. Wait for refresh → Observe stats

**Expected Result**:
- ✅ Stats update correctly for each time period
- ✅ Date badge shows correct range
- ✅ "Custom" shows date picker when selected
- ✅ Orders/Revenue reflect correct time range

**Data Validation**:
```bash
# Verify today's orders:
\App\Models\Order::whereDate('created_at', today())->count();

# Verify this week's orders:
\App\Models\Order::whereBetween('created_at', [
    now()->startOfWeek(),
    now()->endOfWeek()
])->count();
```

---

### TEST 4: Combined Filters (Store + Date)
**Steps**:
1. Set Store = "Toko Cabang A"
2. Set Periode = "Bulan Ini"
3. Observe stats
4. Note values
5. Change to Store = "Semua Cabang"
6. Keep Periode = "Bulan Ini"
7. Observe stats → Should be higher (aggregate)

**Expected Result**:
- ✅ Stats correctly filtered by BOTH store AND date
- ✅ Aggregate (Semua Cabang) > Single Store
- ✅ Summary badges show both filters

---

### TEST 5: Filter Persistence Across Page Navigation
**Steps**:
1. Set filters: Store = "Toko Pusat", Periode = "Minggu Ini"
2. Navigate to **Orders** page (or any other resource)
3. Navigate back to **Dashboard**

**Expected Result**:
- ✅ Filters remain the same (Store = "Toko Pusat", Periode = "Minggu Ini")
- ✅ No reset to default
- ✅ Session persistence working

**Technical Note**:
Filters stored in session:
- `global_filter.tenant_id`
- `global_filter.store_id`
- `global_filter.date_start`
- `global_filter.date_end`
- `global_filter.date_preset`

---

### TEST 6: Reset Filters
**Steps**:
1. Set non-default filters (e.g., Store = "Toko A", Periode = "Kemarin")
2. Click **Reset** button
3. Observe filter values

**Expected Result**:
- ✅ Filters reset to default:
  - Store = "Semua Cabang" (or first store if only one)
  - Periode = "Bulan Ini"
- ✅ Stats refresh with default filters
- ✅ Page reloads

---

### TEST 7: Multi-Tenant Support (Future-Proof)
**Prerequisite**: User must have access to multiple tenants

**Steps**:
1. Login as user with multiple tenants
2. Open Dashboard
3. Check if **Bisnis** (Tenant) dropdown is visible

**Expected Result**:
- ✅ If user has > 1 tenant → Tenant dropdown visible
- ✅ If user has = 1 tenant → Tenant dropdown hidden (auto-selected)
- ✅ Changing tenant resets store filter

**Note**: This feature is currently future-proof; most users have single tenant.

---

### TEST 8: Widget Auto-Refresh on Filter Change
**Steps**:
1. Open browser console (F12 → Console tab)
2. Change store filter
3. Observe console for Livewire events
4. Observe dashboard widgets refreshing

**Expected Result**:
- ✅ Console shows `filter-updated` event
- ✅ Page reload after 500ms
- ✅ All widgets update with new data
- ✅ No JavaScript errors

---

### TEST 9: Edge Case - No Stores for Tenant
**Setup**:
```bash
# Create tenant without stores
$tenant = \App\Models\Tenant::create(['name' => 'Test Empty', 'slug' => 'test-empty']);
```

**Steps**:
1. Login as user of tenant with no stores
2. Access dashboard

**Expected Result**:
- ✅ Dashboard loads without error
- ✅ Stats show "0" or "No Data"
- ✅ Warning message: "No stores found for current tenant"
- ✅ Filter widget visible but store dropdown empty (or shows "No stores available")

---

### TEST 10: Performance Test - Large Dataset
**Setup**:
Create large dataset:
```bash
# Create 1000 orders for testing
php artisan tinker
factory(\App\Models\Order::class, 1000)->create();
```

**Steps**:
1. Set filter to "Bulan Ini" + "Semua Cabang"
2. Measure page load time
3. Change filter to specific store
4. Measure page load time

**Expected Result**:
- ✅ Page loads in < 2 seconds (reasonable for 1000 records)
- ✅ No database timeout errors
- ✅ Widgets render correctly
- ✅ No UI lag

**Performance Monitoring**:
```bash
# Check query performance in logs:
tail -f storage/logs/laravel.log | grep "SELECT"
```

---

## ✅ Test Result Summary

| Test Case | Status | Notes |
|-----------|--------|-------|
| TEST 1: Global Filter Visibility | ⬜ PASS / ⬜ FAIL | |
| TEST 2: Store Filter Change | ⬜ PASS / ⬜ FAIL | |
| TEST 3: Date Filter Change | ⬜ PASS / ⬜ FAIL | |
| TEST 4: Combined Filters | ⬜ PASS / ⬜ FAIL | |
| TEST 5: Filter Persistence | ⬜ PASS / ⬜ FAIL | |
| TEST 6: Reset Filters | ⬜ PASS / ⬜ FAIL | |
| TEST 7: Multi-Tenant Support | ⬜ PASS / ⬜ FAIL | |
| TEST 8: Auto-Refresh | ⬜ PASS / ⬜ FAIL | |
| TEST 9: Edge Case - No Stores | ⬜ PASS / ⬜ FAIL | |
| TEST 10: Performance Test | ⬜ PASS / ⬜ FAIL | |

---

## 🐛 Common Issues & Solutions

### Issue 1: Filter Widget Not Showing
**Symptoms**: Dashboard loads but no Global Filter Widget
**Solutions**:
```bash
# Clear caches
php artisan config:clear
php artisan view:clear

# Verify widget registered in OwnerPanelProvider
# Check: app/Providers/Filament/OwnerPanelProvider.php
```

### Issue 2: Stats Not Updating
**Symptoms**: Stats remain the same despite filter changes
**Solutions**:
- Check browser console for JS errors
- Verify Livewire is loaded
- Check session is working (cookies enabled)
- Verify GlobalFilterService is returning correct values

### Issue 3: "No Data" Despite Having Data
**Symptoms**: Stats show 0 or "No Data" but database has records
**Solutions**:
```bash
# Debug in tinker:
$globalFilter = app(\App\Services\GlobalFilterService::class);
$storeIds = $globalFilter->getStoreIdsForCurrentTenant();
dd($storeIds); // Should return array of store IDs

# Check if stores have correct tenant_id:
\App\Models\Store::whereNull('tenant_id')->count(); // Should be 0
```

### Issue 4: Filter Resets on Page Reload
**Symptoms**: Filter settings not persisting
**Solutions**:
- Check session driver in `.env` (should be `file` or `redis`, not `array`)
- Verify session middleware in OwnerPanelProvider
- Check session files in `storage/framework/sessions/`

---

## 📊 Success Criteria

✅ **PASS Criteria**:
- All 10 test cases pass
- No errors in browser console
- No errors in Laravel logs
- Stats update correctly for each filter combination
- Filter persistence works across page navigation
- Performance acceptable (< 2s page load)

❌ **FAIL Criteria**:
- Any test case fails
- JavaScript errors in console
- PHP errors in logs
- Stats show incorrect data
- Filters reset unexpectedly
- Page load > 5s

---

## 🚀 Next Steps After Testing

If all tests pass:
1. ✅ Migrate remaining widgets to use GlobalFilter
2. ✅ Update Resources (Orders, Products, Customers) to respect filter
3. ✅ Add "Compare Stores" advanced feature
4. ✅ Add export functionality with filter applied

If tests fail:
1. 🐛 Document specific failure
2. 🔍 Debug using tinker/logs
3. 🔧 Fix issues
4. 🔄 Re-test

---

**Tester**: __________________  
**Date**: __________________  
**Environment**: Local / Staging / Production  
**Browser**: Chrome / Firefox / Safari  
**Result**: ⬜ PASS / ⬜ FAIL

**Additional Notes**:
_______________________________________________________
_______________________________________________________
_______________________________________________________

