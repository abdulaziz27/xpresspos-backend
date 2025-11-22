<x-filament-panels::page>
    <div class="space-y-6">
        @if($filterSummary)
            <x-filament::section>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $filterSummary['tenant'] ?? 'Tenant tidak dipilih' }} •
                    {{ $filterSummary['store'] ?? 'Semua Cabang' }} •
                    {{ $filterSummary['date_start'] ?? '' }} - {{ $filterSummary['date_end'] ?? '' }}
                </div>
            </x-filament::section>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Penjualan</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $salesSummary['total_revenue'] ?? 'Rp 0' }}</p>
            </x-filament::section>

            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Jumlah Order</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($salesSummary['total_orders'] ?? 0) }}</p>
            </x-filament::section>

            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Rata-Rata Order</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $salesSummary['average_order_value'] ?? 'Rp 0' }}</p>
            </x-filament::section>

            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Member Aktif</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($salesSummary['unique_customers'] ?? 0) }}</p>
            </x-filament::section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Metode Pembayaran</x-slot>
                <div class="space-y-3">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $paymentBreakdown['total_payments'] ?? 'Rp 0' }}</div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($paymentBreakdown['methods'] ?? [] as $method)
                            <li class="flex items-center justify-between py-2">
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ $method['method'] }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $method['amount'] }}</span>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Belum ada transaksi.</li>
                        @endforelse
                    </ul>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Produk Terlaris</x-slot>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($topProducts as $product)
                        <li class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $product['product_name'] ?? '-' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($product['quantity_sold'] ?? 0) }} terjual</p>
                            </div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                Rp {{ number_format($product['revenue'] ?? 0, 0, ',', '.') }}
                            </div>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Belum ada data produk.</li>
                    @endforelse
                </ul>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Detail Transaksi</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>


