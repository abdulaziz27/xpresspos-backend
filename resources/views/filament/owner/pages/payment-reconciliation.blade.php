<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php
                $storeContext = app(\App\Services\StoreContext::class);
                $storeId = $storeContext->getCurrentStoreId();
                $store = \App\Models\Store::find($storeId);
                $tenantId = $store?->tenant_id;
                
                $baseQuery = \App\Models\SubscriptionPayment::query();
                
                if ($tenantId) {
                    $baseQuery->where(function ($query) use ($tenantId) {
                        $query->whereHas('subscription', function ($subQuery) use ($tenantId) {
                            $subQuery->where('tenant_id', $tenantId);
                        })->orWhereHas('landingSubscription', function ($subQuery) use ($tenantId) {
                            $subQuery->where('tenant_id', $tenantId);
                        });
                    });
                } else {
                    $baseQuery->whereRaw('1 = 0');
                }
                
                $thisMonth = now()->startOfMonth();
                $totalPaid = (clone $baseQuery)->paid()->where('paid_at', '>=', $thisMonth)->sum('amount');
                $totalFees = (clone $baseQuery)->paid()->where('paid_at', '>=', $thisMonth)->sum('gateway_fee');
                $pendingCount = (clone $baseQuery)->pending()->count();
                $unreconciledCount = (clone $baseQuery)->paid()->whereNull('gateway_response')->count();
            @endphp
            
            <x-filament::section class="p-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        Rp {{ number_format($totalPaid, 0, ',', '.') }}
                    </div>
                    <div class="text-sm text-gray-500">Total Paid (This Month)</div>
                </div>
            </x-filament::section>
            
            <x-filament::section class="p-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        Rp {{ number_format($totalPaid - $totalFees, 0, ',', '.') }}
                    </div>
                    <div class="text-sm text-gray-500">Net Revenue</div>
                </div>
            </x-filament::section>
            
            <x-filament::section class="p-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">
                        {{ $pendingCount }}
                    </div>
                    <div class="text-sm text-gray-500">Pending Payments</div>
                </div>
            </x-filament::section>
            
            <x-filament::section class="p-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">
                        {{ $unreconciledCount }}
                    </div>
                    <div class="text-sm text-gray-500">Unreconciled</div>
                </div>
            </x-filament::section>
        </div>

        <!-- Reconciliation Instructions -->
        <x-filament::section>
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Payment Reconciliation
                </h3>
                <div class="prose dark:prose-invert max-w-none">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        This page helps you reconcile your subscription payments with Xendit's records. 
                        Use the sync functions to update payment statuses and ensure your records match the payment gateway.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-900 dark:text-blue-100">Sync Individual Payments</h4>
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Click "Sync Status" on any payment to update its status from Xendit.
                            </p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                            <h4 class="font-medium text-green-900 dark:text-green-100">Bulk Operations</h4>
                            <p class="text-sm text-green-700 dark:text-green-300">
                                Select multiple payments and use "Sync Selected" for bulk updates.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- Payment Table -->
        <x-filament::section>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>