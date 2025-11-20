# Unified Multi-Store Dashboard - Testing Guide

**Date:** November 19, 2025  
**Purpose:** Manual testing guide untuk verify Unified Dashboard implementation

---

## 🎯 Testing Objectives

1. ✅ Verify Global Filter Widget is visible and functional
2. ✅ Verify widgets respect global filter (store + date)
3. ✅ Verify "Semua Cabang" (All Stores) aggregates data correctly
4. ✅ Verify specific store filter shows only that store's data
5. ✅ Verify date preset changes update all widgets
6. ✅ Verify session persistence across page refreshes
7. ✅ Verify OrderResource respects global filter

---

## 🧪 Test Scenarios

### Test 1: Initial Dashboard Load

**Steps:**
1. Login to Owner Panel (`http://127.0.0.1:8000/owner`)
2. Navigate to Dashboard

**Expected Results:**
- ✅ GlobalFilterWidget is visible at the top
- ✅ Shows 3 filter controls: Tenant (if multi), Store, Date Preset
- ✅ Default store: Auto-selected based on tenant
- ✅ Default date: "Bulan Ini" (This Month)
- ✅ Visual summary badges show current filter state
- ✅ All widgets load with data based on default filters

**Screenshots:**
```
[Global Filter Widget]
┌────────────────────────────────────────────────────────────────┐
│ 📊 Filter Global Dashboard                           [Reset]   │
│ Pilih cabang dan periode untuk melihat data yang sesuai.      │
├────────────────────────────────────────────────────────────────┤
│ [Bisnis: Demo Business ▼] [Cabang: Semua Cabang ▼]           │
│ [Periode: Bulan Ini ▼]                                        │
├────────────────────────────────────────────────────────────────┤
│ 🏢 Semua Cabang  📅 01 Nov - 30 Nov  ⏰ Bulan Ini           │
└────────────────────────────────────────────────────────────────┘
```

---

### Test 2: Change Store Filter to "Semua Cabang"

**Steps:**
1. In Global Filter Widget, select Store dropdown
2. Select "🏢 Semua Cabang"

**Expected Results:**
- ✅ Page refreshes or widgets reload
- ✅ All widgets now show aggregated data from ALL stores
- ✅ BestBranchesWidget shows all branches ranked by revenue
- ✅ LowStockWidget shows store name column
- ✅ OwnerStatsWidget description shows "Semua Cabang"

**SQL Query (verify):**
```sql
-- All widgets should use whereIn with ALL store IDs
SELECT * FROM orders WHERE store_id IN ('store-1', 'store-2', 'store-3');
```

---

### Test 3: Change Store Filter to Specific Store

**Steps:**
1. In Global Filter Widget, select Store dropdown
2. Select specific store (e.g., "Toko Cabang A")

**Expected Results:**
- ✅ Page refreshes or widgets reload
- ✅ All widgets now show data ONLY from selected store
- ✅ BestBranchesWidget shows only that branch (or empty if no comparison)
- ✅ LowStockWidget hides store name column
- ✅ OwnerStatsWidget description shows "Toko Cabang A"

**SQL Query (verify):**
```sql
-- All widgets should filter by specific store ID
SELECT * FROM orders WHERE store_id = 'store-1';
```

---

### Test 4: Change Date Preset to "Hari Ini" (Today)

**Steps:**
1. In Global Filter Widget, select Date Preset dropdown
2. Select "Hari Ini"

**Expected Results:**
- ✅ Widgets reload with today's data
- ✅ SalesRevenueChartWidget switches to hourly view (24 hours)
- ✅ OwnerStatsWidget shows "Hari Ini" in description
- ✅ Visual summary badge shows today's date
- ✅ ProfitAnalysisWidget updates to today's profit

**Date Range:**
```
Start: 2025-11-19 00:00:00
End:   2025-11-19 23:59:59
```

---

### Test 5: Change Date Preset to "Minggu Ini" (This Week)

**Steps:**
1. In Global Filter Widget, select Date Preset dropdown
2. Select "Minggu Ini"

**Expected Results:**
- ✅ Widgets reload with this week's data
- ✅ SalesRevenueChartWidget switches to daily view (7 days)
- ✅ Date range: Monday (start of week) to Sunday (end of week)

**Date Range:**
```
Start: 2025-11-17 00:00:00 (Monday)
End:   2025-11-23 23:59:59 (Sunday)
```

---

### Test 6: Custom Date Range

**Steps:**
1. In Global Filter Widget, select Date Preset dropdown
2. Select "Custom"
3. DatePicker field appears
4. Select custom start and end dates (e.g., Nov 1 - Nov 10)
5. Click apply or tab out

**Expected Results:**
- ✅ Widgets reload with custom date range
- ✅ Visual summary badge shows custom date range
- ✅ All time-based metrics respect custom range

**Date Range:**
```
Start: 2025-11-01 00:00:00
End:   2025-11-10 23:59:59
```

---

### Test 7: Session Persistence

**Steps:**
1. Set filters: Store = "Toko Cabang A", Date = "Minggu Ini"
2. Navigate to another page (e.g., Orders)
3. Navigate back to Dashboard
4. **OR** Hard refresh page (Cmd+R / Ctrl+R)

**Expected Results:**
- ✅ Filters remain the same (Store = "Toko Cabang A", Date = "Minggu Ini")
- ✅ Widgets load with the same filter state
- ✅ No reset to default

**Session Keys (verify in browser dev tools):**
```javascript
// Check in Application > Session Storage
global_filter.tenant_id: "tenant-123"
global_filter.store_id: "store-1"
global_filter.date_preset: "this_week"
global_filter.date_start: "2025-11-17"
global_filter.date_end: "2025-11-23"
```

---

### Test 8: Reset Filters

**Steps:**
1. Set custom filters (specific store, custom date)
2. Click "Reset" button in Global Filter Widget

**Expected Results:**
- ✅ Store filter resets to default (first store or "Semua Cabang")
- ✅ Date filter resets to "Bulan Ini"
- ✅ Widgets reload with default filters

---

### Test 9: OrderResource Respects Global Filter

**Steps:**
1. Set global filter: Store = "Toko Cabang A"
2. Navigate to "Orders" (Transaksi) menu

**Expected Results:**
- ✅ Order list shows ONLY orders from "Toko Cabang A"
- ✅ No orders from other stores visible
- ✅ Create new order → store_id auto-set to "Toko Cabang A"

**SQL Query (verify):**
```sql
-- OrderResource uses GlobalFilterService
SELECT * FROM orders WHERE store_id IN ('store-1');
```

**Steps (All Stores):**
1. Set global filter: Store = "Semua Cabang"
2. Navigate to "Orders" (Transaksi) menu

**Expected Results:**
- ✅ Order list shows orders from ALL stores
- ✅ Store column visible in table (to differentiate)

---

### Test 10: Widget Refresh on Filter Change

**Steps:**
1. Open browser dev tools (Network tab)
2. Change store filter from "Semua Cabang" to "Toko Cabang A"
3. Observe network activity

**Expected Results:**
- ✅ Livewire component refresh requests (or full page reload)
- ✅ All widgets update without full page reload (Livewire magic)
- ✅ No console errors
- ✅ `filter-updated` event dispatched (check in Livewire dev tools)

**Livewire Event:**
```javascript
// Listen in browser console
window.addEventListener('filter-updated', () => {
    console.log('Filter updated!');
});
```

---

## 🔍 Verification SQL Queries

### Check Store IDs for Tenant

```sql
-- Get all stores for a tenant
SELECT id, name, status 
FROM stores 
WHERE tenant_id = 'tenant-xxx' AND status = 'active'
ORDER BY name;
```

### Check Orders by Store

```sql
-- Verify orders filtered by store
SELECT id, order_number, store_id, created_at, total_amount
FROM orders
WHERE store_id IN ('store-1', 'store-2')
  AND created_at BETWEEN '2025-11-01' AND '2025-11-30'
ORDER BY created_at DESC
LIMIT 10;
```

### Check Payments by Store

```sql
-- Verify payments filtered by store
SELECT id, store_id, amount, status, created_at
FROM payments
WHERE store_id IN ('store-1', 'store-2')
  AND status = 'completed'
  AND created_at BETWEEN '2025-11-01' AND '2025-11-30'
ORDER BY created_at DESC;
```

### Check Products by Store

```sql
-- Verify products filtered by store
SELECT id, name, store_id, price, stock
FROM products
WHERE store_id IN ('store-1', 'store-2')
  AND status = 1
ORDER BY name;
```

---

## 🐛 Common Issues & Fixes

### Issue 1: Widgets not refreshing when filter changes

**Symptom:** Change filter, but widgets still show old data

**Fix:**
- Check `#[On('filter-updated')]` attribute in widget
- Verify `$this->resetState()` or `$this->resetTable()` is called
- Check browser console for Livewire errors

### Issue 2: Filter not persisting across pages

**Symptom:** Navigate to another page, filter resets

**Fix:**
- Check session middleware is active
- Verify `GlobalFilterService` uses `Session::put()` correctly
- Check browser cookies/session storage

### Issue 3: "Semua Cabang" shows no data

**Symptom:** Select "All Stores", but widgets show empty

**Fix:**
- Check `getStoreIdsForCurrentTenant()` returns array of store IDs
- Verify `whereIn('store_id', $storeIds)` is used (not `where('store_id', ...)`)
- Check if user has stores assigned to tenant

### Issue 4: Date range not applying

**Symptom:** Change date preset, but widgets show all-time data

**Fix:**
- Check `getCurrentDateRange()` returns correct start/end dates
- Verify `whereBetween('created_at', [$start, $end])` is used
- Check timezone settings in `config/app.php`

---

## 📊 Expected Widget Data Examples

### OwnerStatsWidget (Semua Cabang, Bulan Ini)

```
┌─────────────────────────────────────────────────────────────┐
│ Total Transaksi                                            │
│ 1,234                                                      │
│ Semua Cabang • Bulan Ini                                   │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Total Pendapatan                                           │
│ Rp 12,345,000                                              │
│ Semua Cabang • Bulan Ini                                   │
└─────────────────────────────────────────────────────────────┘
```

### BestBranchesWidget (Semua Cabang, Bulan Ini)

```
┌─────────────────────────────────────────────────────────────┐
│ Cabang dengan Penjualan Terbaik                            │
├───────────────────┬──────────────────┬─────────────────────┤
│ Cabang            │ Pendapatan       │ Transaksi          │
├───────────────────┼──────────────────┼─────────────────────┤
│ Toko Cabang A     │ Rp 5,000,000    │ 500                │
│ Toko Cabang B     │ Rp 4,500,000    │ 450                │
│ Toko Cabang C     │ Rp 2,845,000    │ 284                │
└───────────────────┴──────────────────┴─────────────────────┘
```

### TopMenuTableWidget (Toko Cabang A, Hari Ini)

```
┌─────────────────────────────────────────────────────────────┐
│ Produk Terlaris (Top 10)                                   │
├────────────────────────────────────┬────────────────────────┤
│ Produk                             │ Terjual               │
├────────────────────────────────────┼────────────────────────┤
│ Nasi Goreng Special                │ 45                    │
│ Es Teh Manis                       │ 42                    │
│ Ayam Bakar                         │ 38                    │
└────────────────────────────────────┴────────────────────────┘
```

---

## ✅ Success Criteria

Dashboard implementation is successful if:

1. ✅ GlobalFilterWidget visible and functional
2. ✅ All widgets refresh when filter changes
3. ✅ "Semua Cabang" aggregates data from all stores
4. ✅ Specific store filter shows only that store's data
5. ✅ Date presets (Today, This Week, This Month) work correctly
6. ✅ Custom date range works
7. ✅ Filters persist across page refreshes
8. ✅ OrderResource respects global filter
9. ✅ No console errors
10. ✅ Performance is acceptable (<2s for widget refresh)

---

## 📝 Testing Log Template

**Tester:** [Your Name]  
**Date:** [Date]  
**Environment:** [Local / Staging / Production]

| Test # | Test Name                | Status | Notes |
|--------|--------------------------|--------|-------|
| 1      | Initial Dashboard Load   | ✅/❌   |       |
| 2      | All Stores Filter        | ✅/❌   |       |
| 3      | Specific Store Filter    | ✅/❌   |       |
| 4      | Date Preset: Today       | ✅/❌   |       |
| 5      | Date Preset: This Week   | ✅/❌   |       |
| 6      | Custom Date Range        | ✅/❌   |       |
| 7      | Session Persistence      | ✅/❌   |       |
| 8      | Reset Filters            | ✅/❌   |       |
| 9      | OrderResource Filter     | ✅/❌   |       |
| 10     | Widget Refresh Event     | ✅/❌   |       |

**Overall Result:** ✅ PASS / ❌ FAIL

**Comments:**
[Add any additional observations, bugs found, or suggestions]

---

**Last Updated:** November 19, 2025

