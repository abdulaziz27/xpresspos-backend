# Unified Multi-Store Dashboard Implementation

**Date:** November 19, 2025  
**Status:** ✅ **IMPLEMENTED**

## 📋 Overview

XpressPOS menggunakan **Unified Multi-Store Dashboard** architecture, terinspirasi dari:
- ESB / EsbPOS
- Moka Backoffice
- DealPOS
- Square Retail
- Shopify POS multi-location

**Konsep:** Semua menu (Products, Orders, Reports, Customers, Inventory, dll) tetap di **1 dashboard**, tetapi kontennya berubah berdasarkan **filter global** (Store / Tenant / Date Range).

## 🎯 Benefits

✅ **Modern UX**: Single dashboard, tidak perlu berpindah-pindah panel  
✅ **Scalable**: Mudah menambahkan tenant/store baru  
✅ **Unified Reporting**: Lihat performa semua cabang atau specific cabang  
✅ **Simple Navigation**: Tidak ada menu terpisah per cabang  
✅ **Filter-Based**: Konten auto-update saat filter berubah

---

## 🧩 Architecture Components

### 1. GlobalFilterService

**Path:** `app/Services/GlobalFilterService.php`

**Purpose:** Centralized service untuk manage global filter state (Tenant, Store, Date Range)

**Key Methods:**
```php
// Get current filters
getCurrentTenantId(): ?string
getCurrentStoreId(): ?string          // null = "All Stores"
getCurrentDateRange(): array          // ['start' => Carbon, 'end' => Carbon]

// Get available options
getAvailableStores(): Collection
getAvailableDatePresets(): array

// Set filters
setTenant(string $tenantId): void
setStore(?string $storeId): void      // null = "All Stores"
setDatePreset(string $preset): void

// Query helpers
getStoreIdsForCurrentTenant(): array  // For whereIn('store_id', ...)
getQueryConstraints(): array          // For where($constraints)
applyDateRangeToQuery($query, string $dateColumn = 'created_at')

// Filter summary
getFilterSummary(): array             // For display
```

**Session Keys:**
- `global_filter.tenant_id`
- `global_filter.store_id`
- `global_filter.date_start`
- `global_filter.date_end`
- `global_filter.date_preset`

**Date Presets:**
- `today` - Hari Ini
- `yesterday` - Kemarin
- `this_week` - Minggu Ini
- `last_week` - Minggu Lalu
- `this_month` - Bulan Ini
- `last_month` - Bulan Lalu
- `this_year` - Tahun Ini
- `custom` - Custom Range

---

### 2. GlobalFilterWidget

**Path:** `app/Filament/Owner/Widgets/GlobalFilterWidget.php`

**Purpose:** UI Component untuk global filter di dashboard

**Features:**
- Dropdown **Tenant** (visible only if user has multiple tenants)
- Dropdown **Store** (Semua Cabang + individual stores)
- Select **Date Preset** (Today, This Week, This Month, Custom)
- DatePicker **Custom Range** (visible when preset = 'custom')
- **Real-time Update**: Semua widget/resource auto-refresh saat filter berubah
- **Visual Summary**: Badge yang menampilkan filter aktif
- **Reset Button**: Reset to default

**Sort:** `-9999` (Always at top of dashboard)

**Livewire Events:**
- Emit: `filter-updated` → trigger refresh all widgets
- Listen: `reset-filters` → reset to default

---

### 3. Updated Widgets

All dashboard widgets now use `GlobalFilterService`:

#### ✅ OwnerStatsWidget
- Total Transaksi (per store, date filtered)
- Total Pendapatan (per store, date filtered)
- Total Produk (all time)
- Member Aktif (all time)

#### ✅ ProfitAnalysisWidget
- Uses `FnBAnalyticsService::getProfitAnalysisForStores()`
- Shows profit across selected stores
- Date range from global filter

#### ✅ SalesRevenueChartWidget
- Bar chart with aggregated data
- Auto-adjust granularity (hourly vs daily)
- Combined data from all selected stores

#### ✅ TopMenuTableWidget
- Top 10 products by quantity sold
- Aggregated across selected stores
- Date range from global filter

#### ✅ BestBranchesWidget
- Revenue per branch
- Date range from global filter
- Shows all branches when "All Stores" selected

#### ✅ LowStockWidget
- Low stock alerts across stores
- Shows store name column when "All Stores" selected

**Common Pattern:**
```php
#[On('filter-updated')]
public function refreshWidget(): void
{
    $this->resetState(); // or $this->resetTable() for TableWidget
}

protected function getData(): array
{
    $globalFilter = app(GlobalFilterService::class);
    
    $storeIds = $globalFilter->getStoreIdsForCurrentTenant();
    $dateRange = $globalFilter->getCurrentDateRange();
    $summary = $globalFilter->getFilterSummary();
    
    // Query logic using $storeIds and $dateRange
}
```

---

### 4. Updated Resources

Resources that respect global filter:

#### ✅ OrderResource
```php
public static function getEloquentQuery(): Builder
{
    $globalFilter = app(GlobalFilterService::class);
    $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

    $query = parent::getEloquentQuery();

    if (!empty($storeIds)) {
        $query->whereIn('store_id', $storeIds);
    }

    return $query;
}
```

#### 🔄 ProductResource (TODO)
Currently using `$user->store_id` (single store)  
**Migration needed:**
- Use `GlobalFilterService::getStoreIdsForCurrentTenant()`
- Support multi-store view

#### 🔄 TableResource (TODO)
Currently using `$user->store_id` (single store)  
**Migration needed:**
- Use `GlobalFilterService::getStoreIdsForCurrentTenant()`
- Support multi-store view

#### 🔄 Other Resources (TODO)
Resources to migrate:
- Categories
- Members
- Payments
- CogsHistory
- Expenses
- InventoryMovements
- Discounts
- Refunds
- CashSessions

---

## 🚀 Implementation Guide

### For New Widgets

```php
use App\Services\GlobalFilterService;
use Livewire\Attributes\On;

class MyNewWidget extends BaseWidget
{
    #[On('filter-updated')]
    public function refreshWidget(): void
    {
        $this->resetState();
    }

    protected function getData(): array
    {
        $globalFilter = app(GlobalFilterService::class);
        
        // Get filter values
        $tenantId = $globalFilter->getCurrentTenantId();
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();
        $dateRange = $globalFilter->getCurrentDateRange();
        $summary = $globalFilter->getFilterSummary();
        
        // Query with filters
        $data = MyModel::whereIn('store_id', $storeIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get();
        
        return [
            // widget data
        ];
    }
}
```

### For Resources

```php
use App\Services\GlobalFilterService;
use Illuminate\Database\Eloquent\Builder;

class MyResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

        $query = parent::getEloquentQuery();

        if (!empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $query;
    }
}
```

### For Custom Queries

```php
use App\Services\GlobalFilterService;

$globalFilter = app(GlobalFilterService::class);

// Get store IDs
$storeIds = $globalFilter->getStoreIdsForCurrentTenant();

// Apply to query
$query = Order::whereIn('store_id', $storeIds);

// Apply date range
$globalFilter->applyDateRangeToQuery($query, 'created_at');

// Get results
$results = $query->get();
```

---

## 🧪 Testing

### Manual Testing Checklist

**Scenario 1: Single Store**
- ✅ Login as owner with 1 store
- ✅ Dashboard shows data from that store only
- ✅ Changing date preset updates widgets
- ✅ Custom date range works

**Scenario 2: Multiple Stores**
- ✅ Login as owner with multiple stores
- ✅ Select "Semua Cabang" → data aggregated from all stores
- ✅ Select specific store → data filtered to that store only
- ✅ BestBranchesWidget shows all branches ranked
- ✅ Store filter persists across page refresh

**Scenario 3: Widget Refresh**
- ✅ Change store filter → all widgets refresh
- ✅ Change date preset → all widgets refresh
- ✅ Change custom date range → all widgets refresh
- ✅ Reset button → back to defaults

**Scenario 4: Resources**
- ✅ OrderResource → filtered by selected store(s)
- ✅ Create new order → belongs to current store context
- ✅ Edit existing order → respects permissions

---

## 📊 Filter State Flow

```
User Login
    ↓
GlobalFilterService::getCurrentTenantId()
    → Check session
    → Fallback to user's currentTenant()
    → Auto-save to session
    ↓
GlobalFilterWidget mounted
    → Load current filters
    → Display in UI
    ↓
User changes filter
    → GlobalFilterService::setStore() / setDatePreset()
    → Session updated
    → Emit 'filter-updated' event
    ↓
All widgets listening to 'filter-updated'
    → resetState() / resetTable()
    → Re-fetch data with new filters
    ↓
Page refresh (optional)
    → Filters loaded from session
    → Consistent state
```

---

## 🔄 Migration Strategy (For Existing Code)

### Phase 1: Core Widgets (✅ DONE)
- GlobalFilterWidget
- OwnerStatsWidget
- ProfitAnalysisWidget
- SalesRevenueChartWidget
- TopMenuTableWidget
- BestBranchesWidget
- LowStockWidget

### Phase 2: Primary Resources (IN PROGRESS)
- OrderResource ✅
- ProductResource 🔄
- PaymentResource 🔄
- MemberResource 🔄

### Phase 3: Secondary Resources
- Categories
- Tables
- CogsHistory
- Expenses
- InventoryMovements
- Discounts
- Refunds
- CashSessions

### Phase 4: Reporting & Analytics
- Custom Reports
- Export functionality
- Dashboard analytics

---

## 🛡️ Best Practices

1. **Always use GlobalFilterService**
   - Don't hardcode `auth()->user()->store_id`
   - Use `getStoreIdsForCurrentTenant()` for queries

2. **Listen to filter-updated event**
   - All widgets must listen to `#[On('filter-updated')]`
   - Reset state when filter changes

3. **Show store column when appropriate**
   - When "All Stores" selected, show store name in tables
   - Use `visible(fn() => !$globalFilter->getCurrentStoreId())`

4. **Date range awareness**
   - Use `getCurrentDateRange()` for time-based metrics
   - Use `applyDateRangeToQuery()` helper

5. **Filter summary in labels**
   - Add filter context to widget descriptions
   - Example: "Bulan Ini • Semua Cabang"

6. **Handle empty stores gracefully**
   - Check if `$storeIds` is empty
   - Return empty state with helpful message

---

## 🔗 Related Documentation

- `docs/UPGRADE_DOWNGRADE_IMPLEMENTATION_SUMMARY.md`
- `docs/UPGRADE_DOWNGRADE_FEATURE_GATING.md`
- `app/Services/GlobalFilterService.php`
- `app/Filament/Owner/Widgets/GlobalFilterWidget.php`

---

## 📝 Notes

- **Session-based**: Filter state stored in session, persists across requests
- **Tenant-scoped**: Always filter by tenant first, then by store
- **Backward compatible**: Existing single-store code still works
- **Multi-tenant ready**: Architecture supports multiple tenants per user (future)
- **Real-time updates**: Livewire events for instant UI refresh
- **No page reload needed**: Filters update widgets instantly (optional page reload for full refresh)

---

**Status:** Dashboard implementation complete. Next: Migrate remaining resources to use GlobalFilterService.

**Last Updated:** November 19, 2025
