<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

/**
 * DashboardFilterService
 * 
 * Service untuk mengelola filter dashboard (Tenant, Store, Date Range) di Filament Owner Dashboard.
 * Terpisah dari GlobalFilterService agar tidak mempengaruhi resource pages.
 */
class DashboardFilterService
{
    const SESSION_KEY_TENANT = 'dashboard_filter.tenant_id';
    const SESSION_KEY_STORE = 'dashboard_filter.store_id';
    const SESSION_KEY_DATE_START = 'dashboard_filter.date_start';
    const SESSION_KEY_DATE_END = 'dashboard_filter.date_end';
    const SESSION_KEY_DATE_PRESET = 'dashboard_filter.date_preset';

    /**
     * Get current tenant ID from dashboard filter (or auto-detect)
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
     * Get current store ID from dashboard filter
     * 
     * @return string|null Returns null if "All Stores" is selected
     */
    public function getCurrentStoreId(?User $user = null): ?string
    {
        $user = $user ?? auth()->user();
        
        if (!$user) {
            return null;
        }

        $storeId = Session::get(self::SESSION_KEY_STORE);
        
        if ($storeId === 'all' || $storeId === null) {
            return null;
        }

        // Verify store belongs to current tenant
        $tenantId = $this->getCurrentTenantId($user);
        if ($tenantId && $storeId) {
            $store = Store::where('id', $storeId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
            
            if ($store) {
                return $storeId;
            }
        }

        return null;
    }

    /**
     * Get current store object
     */
    public function getCurrentStore(?User $user = null): ?Store
    {
        $storeId = $this->getCurrentStoreId($user);
        
        if (!$storeId) {
            return null;
        }

        return Store::find($storeId);
    }

    /**
     * Get store IDs for current tenant (for dashboard widgets)
     * Returns array with single store ID if specific store selected,
     * or all store IDs if "All Stores" selected
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
     * Get current date range from dashboard filter
     */
    public function getCurrentDateRange(): array
    {
        $preset = $this->getCurrentDatePreset() ?? 'this_month';
        $range = $this->getDateRangeFromPreset($preset);

        // Override with custom dates if preset is 'custom'
        $startDate = Session::get(self::SESSION_KEY_DATE_START);
        $endDate = Session::get(self::SESSION_KEY_DATE_END);

        if ($preset === 'custom' && $startDate && $endDate) {
            return [
                'start' => Carbon::parse($startDate)->startOfDay(),
                'end' => Carbon::parse($endDate)->endOfDay(),
            ];
        }

        return $range;
    }

    /**
     * Get current date preset
     */
    public function getCurrentDatePreset(): ?string
    {
        return Session::get(self::SESSION_KEY_DATE_PRESET, 'this_month');
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
                'start' => Carbon::today()->startOfDay(),
                'end' => Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                'start' => Carbon::yesterday()->startOfDay(),
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
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
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
            'custom' => 'Custom Range',
        ];
    }

    /**
     * Get date range for a specific preset
     */
    public function getDateRangeForPreset(string $preset): array
    {
        return $this->getDateRangeFromPreset($preset);
    }

    /**
     * Reset all dashboard filters
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
            'date_preset_label' => $this->getAvailableDatePresets()[$preset ?? 'this_month'] ?? 'Custom',
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
     * Sync dashboard filter payload into session-backed dashboard filter.
     *
     * @param  array<string, mixed>  $filters
     */
    public function syncFromDashboardFilters(array $filters, ?User $user = null): void
    {
        $user = $user ?? auth()->user();

        $tenantId = $filters['tenant_id'] ?? null;
        $this->setTenant($tenantId, $user);

        if (array_key_exists('store_id', $filters)) {
            $this->setStore($filters['store_id']);
        }

        if (isset($filters['date_preset'])) {
            if ($filters['date_preset'] === 'custom') {
                if (isset($filters['date_start']) && isset($filters['date_end'])) {
                    $this->setDateRange(
                        Carbon::parse($filters['date_start']),
                        Carbon::parse($filters['date_end']),
                        'custom'
                    );
                }
            } else {
                $this->setDatePreset($filters['date_preset']);
            }
        }
    }

    /**
     * Get available stores for current tenant
     */
    public function getAvailableStores(?User $user = null): \Illuminate\Database\Eloquent\Collection
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
}

