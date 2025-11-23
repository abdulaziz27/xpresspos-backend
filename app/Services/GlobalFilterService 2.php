<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

/**
 * GlobalFilterService
 * 
 * Service untuk mengelola global filter (Tenant, Store, Date Range) di Filament Owner Dashboard.
 * Unified approach: 1 dashboard, filter-based content.
 */
class GlobalFilterService
{
    const SESSION_KEY_TENANT = 'global_filter.tenant_id';
    const SESSION_KEY_STORE = 'global_filter.store_id';
    const SESSION_KEY_DATE_START = 'global_filter.date_start';
    const SESSION_KEY_DATE_END = 'global_filter.date_end';
    const SESSION_KEY_DATE_PRESET = 'global_filter.date_preset';

    /**
     * Get current tenant ID from filter (or auto-detect)
     */
    public function getCurrentTenantId(?User $user = null): ?string
    {
        $user = $user ?? auth()->user();
        
        if (!$user) {
            return null;
        }

        // Check session first
        $sessionTenantId = Session::get(self::SESSION_KEY_TENANT);
        
        if ($sessionTenantId) {
            // Verify user has access to this tenant
            if ($user->tenants()->where('tenant_id', $sessionTenantId)->exists()) {
                return $sessionTenantId;
            }
        }

        // Fallback: Get user's primary tenant
        $tenant = $user->currentTenant();
        
        if ($tenant) {
            // Auto-set to session for consistency
            Session::put(self::SESSION_KEY_TENANT, $tenant->id);
            return $tenant->id;
        }

        return null;
    }

    /**
     * Get current tenant object
     */
    public function getCurrentTenant(?User $user = null): ?Tenant
    {
        $tenantId = $this->getCurrentTenantId($user);
        
        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Get current store ID from filter
     * 
     * @return string|null Returns null for "All Stores", or specific store ID
     */
    public function getCurrentStoreId(): ?string
    {
        return Session::get(self::SESSION_KEY_STORE);
    }

    /**
     * Get current store object (null if "All Stores" selected)
     */
    public function getCurrentStore(): ?Store
    {
        $storeId = $this->getCurrentStoreId();
        
        if (!$storeId) {
            return null; // All stores
        }

        return Store::find($storeId);
    }

    /**
     * Get available stores for current tenant
     */
    public function getAvailableStores(?User $user = null): \Illuminate\Support\Collection
    {
        $tenantId = $this->getCurrentTenantId($user);
        
        if (!$tenantId) {
            return collect();
        }

        return Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get current date range
     * 
     * @return array ['start' => Carbon, 'end' => Carbon]
     */
    public function getCurrentDateRange(): array
    {
        $start = Session::get(self::SESSION_KEY_DATE_START);
        $end = Session::get(self::SESSION_KEY_DATE_END);

        // Parse dates
        $startDate = $start ? Carbon::parse($start) : Carbon::today();
        $endDate = $end ? Carbon::parse($end) : Carbon::today()->endOfDay();

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Get current date preset (e.g., 'today', 'this_week', 'this_month')
     */
    public function getCurrentDatePreset(): ?string
    {
        return Session::get(self::SESSION_KEY_DATE_PRESET);
    }

    /**
     * Set tenant filter
     */
    public function setTenant(?string $tenantId, ?User $user = null): void
    {
        $user = $user ?? auth()->user();

        if (blank($tenantId)) {
            Session::forget(self::SESSION_KEY_TENANT);
            Session::forget(self::SESSION_KEY_STORE);

            return;
        }

        // Validate tenant access for current user
        if ($user && ! $user->tenants()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        Session::put(self::SESSION_KEY_TENANT, $tenantId);
        
        // Reset store when tenant changes
        Session::forget(self::SESSION_KEY_STORE);
    }

    /**
     * Set store filter
     * 
     * @param string|null $storeId Pass null for "All Stores"
     */
    public function setStore(?string $storeId): void
    {
        if ($storeId === null || $storeId === 'all') {
            Session::forget(self::SESSION_KEY_STORE);
        } else {
            Session::put(self::SESSION_KEY_STORE, $storeId);
        }
    }

    /**
     * Set date range filter
     */
    public function setDateRange(Carbon $start, Carbon $end, ?string $preset = null): void
    {
        Session::put(self::SESSION_KEY_DATE_START, $start->toDateString());
        Session::put(self::SESSION_KEY_DATE_END, $end->toDateString());
        
        if ($preset) {
            Session::put(self::SESSION_KEY_DATE_PRESET, $preset);
        } else {
            Session::forget(self::SESSION_KEY_DATE_PRESET);
        }
    }

    /**
     * Set date preset (today, yesterday, this_week, this_month, custom)
     */
    public function setDatePreset(string $preset): void
    {
        $range = $this->getDateRangeFromPreset($preset);
        
        $this->setDateRange($range['start'], $range['end'], $preset);
    }

    /**
     * Get date range from preset
     */
    protected function getDateRangeFromPreset(string $preset): array
    {
        return match($preset) {
            'today' => [
                'start' => Carbon::today(),
                'end' => Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                'start' => Carbon::yesterday(),
                'end' => Carbon::yesterday()->endOfDay(),
            ],
            'this_week' => [
                'start' => Carbon::now()->startOfWeek(),
                'end' => Carbon::now()->endOfWeek(),
            ],
            'last_week' => [
                'start' => Carbon::now()->subWeek()->startOfWeek(),
                'end' => Carbon::now()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ],
            'last_month' => [
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                'start' => Carbon::now()->startOfYear(),
                'end' => Carbon::now()->endOfYear(),
            ],
            default => [
                'start' => Carbon::today(),
                'end' => Carbon::today()->endOfDay(),
            ],
        };
    }

    /**
     * Get available date presets
     */
    public function getAvailableDatePresets(): array
    {
        return [
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'this_week' => 'Minggu Ini',
            'last_week' => 'Minggu Lalu',
            'this_month' => 'Bulan Ini',
            'last_month' => 'Bulan Lalu',
            'this_year' => 'Tahun Ini',
            'custom' => 'Custom',
        ];
    }

    /**
     * Reset all filters to default
     */
    public function reset(?User $user = null): void
    {
        Session::forget([
            self::SESSION_KEY_TENANT,
            self::SESSION_KEY_STORE,
            self::SESSION_KEY_DATE_START,
            self::SESSION_KEY_DATE_END,
            self::SESSION_KEY_DATE_PRESET,
        ]);

        // Re-initialize with defaults
        $this->getCurrentTenantId($user);
        $this->setDatePreset('this_month');
    }

    /**
     * Build query constraints for current filter
     * 
     * Usage:
     * $query->where($globalFilter->getQueryConstraints());
     */
    public function getQueryConstraints(): array
    {
        $constraints = [];

        // Tenant constraint (always required)
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId) {
            $constraints['tenant_id'] = $tenantId;
        }

        // Store constraint (optional, skip if "All Stores")
        $storeId = $this->getCurrentStoreId();
        if ($storeId) {
            $constraints['store_id'] = $storeId;
        }

        return $constraints;
    }

    /**
     * Get store IDs for current tenant
     * Useful for legacy models without tenant_id
     */
    public function getStoreIdsForCurrentTenant(): array
    {
        $tenantId = $this->getCurrentTenantId();
        
        if (!$tenantId) {
            return [];
        }

        $storeId = $this->getCurrentStoreId();

        // If specific store selected, return only that store
        if ($storeId) {
            return [$storeId];
        }

        // Return all stores for tenant
        return Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Apply date range to query builder
     * 
     * Usage:
     * $query = Order::query();
     * $globalFilter->applyDateRangeToQuery($query, 'created_at');
     */
    public function applyDateRangeToQuery($query, string $dateColumn = 'created_at')
    {
        $range = $this->getCurrentDateRange();
        
        return $query->whereBetween($dateColumn, [
            $range['start'],
            $range['end'],
        ]);
    }

    /**
     * Get filter summary for display
     */
    public function getFilterSummary(): array
    {
        $tenant = $this->getCurrentTenant();
        $store = $this->getCurrentStore();
        $range = $this->getCurrentDateRange();
        $preset = $this->getCurrentDatePreset();

        return [
            'tenant' => $tenant?->name ?? 'N/A',
            'tenant_id' => $tenant?->id,
            'store' => $store?->name ?? 'Semua Cabang',
            'store_id' => $store?->id,
            'date_start' => $range['start']->format('d M Y'),
            'date_end' => $range['end']->format('d M Y'),
            'date_preset' => $preset,
            'date_preset_label' => $this->getAvailableDatePresets()[$preset ?? 'today'] ?? 'Custom',
        ];
    }

    /**
     * Get current filter state as simple array.
     */
    public function getFilterState(): array
    {
        $range = $this->getCurrentDateRange();

        return [
            'tenant_id' => $this->getCurrentTenantId(),
            'store_id' => $this->getCurrentStoreId(),
            'date_preset' => $this->getCurrentDatePreset() ?? 'this_month',
            'date_start' => $range['start']->toDateString(),
            'date_end' => $range['end']->toDateString(),
        ];
    }

    /**
     * Sync dashboard filter payload into session-backed global filter.
     *
     * @param  array<string, mixed>  $filters
     */
    public function syncFromDashboardFilters(array $filters, ?User $user = null): void
    {
        $user = $user ?? auth()->user();

        $tenantId = $filters['tenant_id'] ?? null;
        $this->setTenant($tenantId, $user);

        if (array_key_exists('store_id', $filters)) {
            $this->setStore($filters['store_id'] ?: null);
        }

        $preset = $filters['date_preset'] ?? null;
        $isCustom = $preset === 'custom';

        if (! $isCustom) {
            $this->setDatePreset($preset ?? 'this_month');

            return;
        }

        $start = $filters['date_start'] ?? null;
        $end = $filters['date_end'] ?? null;

        if (! $start || ! $end) {
            return;
        }

        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $this->setDateRange($startDate, $endDate, 'custom');
    }

    /**
     * Public helper for retrieving preset date ranges.
     */
    public function getDateRangeForPreset(string $preset): array
    {
        return $this->getDateRangeFromPreset($preset);
    }
}

