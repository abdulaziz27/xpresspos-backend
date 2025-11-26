<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanLimitService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PlanUsageWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Penggunaan Plan';

    protected ?string $description = 'Ringkasan penggunaan limit plan Anda';

    public function updatedPageFilters(): void
    {
        $this->cachedStats = null;
    }

    protected function getStats(): array
    {
        $filters = $this->dashboardFilters();
        $tenantId = $filters['tenant_id'] ?? null;

        if (!$tenantId) {
            return [
                Stat::make('Pilih Tenant', 'Untuk melihat usage')
                    ->description('Pilih tenant di filter dashboard')
                    ->color('warning'),
            ];
        }

        $tenant = Tenant::with('plan')->find($tenantId);
        
        if (!$tenant || !$tenant->plan) {
            return [
                Stat::make('Plan Tidak Ditemukan', 'Tidak ada plan aktif')
                    ->description('Hubungi support untuk aktivasi plan')
                    ->color('danger'),
            ];
        }

        $planLimitService = app(PlanLimitService::class);
        $stats = [];

        // Products Usage
        $currentProducts = Product::where('tenant_id', $tenantId)->count();
        $productLimit = $planLimitService->limit($tenant, 'MAX_PRODUCTS');
        $isUnlimited = $productLimit === -1 || $productLimit === null;
        $productUsage = !$isUnlimited && $productLimit > 0 ? ($currentProducts / $productLimit) * 100 : 0;
        $productColor = $isUnlimited ? 'success' : ($productUsage >= 90 ? 'danger' : ($productUsage >= 75 ? 'warning' : 'success'));

        $stats[] = Stat::make('Produk', $isUnlimited ? "{$currentProducts} / Unlimited" : "{$currentProducts} / {$productLimit}")
            ->description($isUnlimited ? 'Unlimited' : number_format($productUsage, 1) . '% digunakan')
            ->descriptionIcon($isUnlimited ? 'heroicon-m-check-circle' : ($productUsage >= 90 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'))
            ->color($productColor)
            ->chart($isUnlimited ? [] : [$productUsage]);

        // Staff Usage
        $storeIds = Store::where('tenant_id', $tenantId)->pluck('id');
        $currentStaff = User::whereHas('storeAssignments', function ($q) use ($storeIds) {
            $q->whereIn('store_id', $storeIds);
        })
        ->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'admin_sistem');
        })
        ->distinct()
        ->count();
        
        $staffLimit = $planLimitService->limit($tenant, 'MAX_STAFF');
        $isUnlimited = $staffLimit === -1 || $staffLimit === null;
        $staffUsage = !$isUnlimited && $staffLimit > 0 ? ($currentStaff / $staffLimit) * 100 : 0;
        $staffColor = $isUnlimited ? 'success' : ($staffUsage >= 90 ? 'danger' : ($staffUsage >= 75 ? 'warning' : 'success'));

        $stats[] = Stat::make('Staff', $isUnlimited ? "{$currentStaff} / Unlimited" : "{$currentStaff} / {$staffLimit}")
            ->description($isUnlimited ? 'Unlimited' : number_format($staffUsage, 1) . '% digunakan')
            ->descriptionIcon($isUnlimited ? 'heroicon-m-check-circle' : ($staffUsage >= 90 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'))
            ->color($staffColor)
            ->chart($isUnlimited ? [] : [$staffUsage]);

        // Stores Usage
        $currentStores = Store::where('tenant_id', $tenantId)->count();
        $storeLimit = $planLimitService->limit($tenant, 'MAX_STORES');
        $isUnlimited = $storeLimit === -1 || $storeLimit === null;
        $storeUsage = !$isUnlimited && $storeLimit > 0 ? ($currentStores / $storeLimit) * 100 : 0;
        $storeColor = $isUnlimited ? 'success' : ($storeUsage >= 90 ? 'danger' : ($storeUsage >= 75 ? 'warning' : 'success'));

        $stats[] = Stat::make('Toko', $isUnlimited ? "{$currentStores} / Unlimited" : "{$currentStores} / {$storeLimit}")
            ->description($isUnlimited ? 'Unlimited' : number_format($storeUsage, 1) . '% digunakan')
            ->descriptionIcon($isUnlimited ? 'heroicon-m-check-circle' : ($storeUsage >= 90 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'))
            ->color($storeColor)
            ->chart($isUnlimited ? [] : [$storeUsage]);

        // Transactions Usage (Monthly)
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $currentTransactions = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        
        $transactionLimit = $planLimitService->limit($tenant, 'MAX_TRANSACTIONS_PER_MONTH');
        $isUnlimited = $transactionLimit === -1 || $transactionLimit === null;
        $transactionUsage = !$isUnlimited && $transactionLimit > 0 ? ($currentTransactions / $transactionLimit) * 100 : 0;
        $transactionColor = $isUnlimited ? 'success' : ($transactionUsage >= 90 ? 'danger' : ($transactionUsage >= 75 ? 'warning' : 'success'));

        $stats[] = Stat::make('Transaksi Bulan Ini', $isUnlimited ? "{$currentTransactions} / Unlimited" : "{$currentTransactions} / {$transactionLimit}")
            ->description($isUnlimited ? 'Unlimited' : number_format($transactionUsage, 1) . '% digunakan')
            ->descriptionIcon($isUnlimited ? 'heroicon-m-check-circle' : ($transactionUsage >= 90 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'))
            ->color($transactionColor)
            ->chart($isUnlimited ? [] : [$transactionUsage]);

        return $stats;
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $tenant = $user->currentTenant();
        return (bool) $tenant;
    }
}

