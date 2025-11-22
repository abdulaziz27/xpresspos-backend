<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Penjualan
        </x-slot>
        
        <div class="space-y-4">
            <div class="space-y-3">
                <div class="flex justify-between items-start gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Penjualan Kotor</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->salesSummary['gross_sales'] ?? 'Rp 0' }}</span>
                </div>
                
                <div class="flex justify-between items-start gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Penjualan Bersih</span>
                    <span class="text-lg font-semibold text-success-600 dark:text-success-400 text-right flex-shrink-0">{{ $this->salesSummary['net_sales'] ?? 'Rp 0' }}</span>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Diskon</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Diskon Nota</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->salesSummary['order_discount'] ?? 'Rp 0' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Diskon Menu</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->salesSummary['item_discount'] ?? 'Rp 0' }}</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-gray-200 dark:border-gray-700 pt-2 mt-2 gap-8">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-shrink-0">Total Diskon</span>
                        <span class="text-sm font-semibold text-danger-600 dark:text-danger-400 text-right flex-shrink-0">{{ $this->salesSummary['total_discount'] ?? 'Rp 0' }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                <div class="flex justify-between items-center gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Total Bill</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->salesSummary['total_bills'] ?? '0' }}</span>
                </div>
                <div class="flex justify-between items-center gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Ukuran Bill</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->salesSummary['avg_bill'] ?? 'Rp 0' }}</span>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="flex justify-between items-center gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Total Penerimaan</span>
                    <span class="text-2xl font-bold text-success-600 dark:text-success-400 text-right flex-shrink-0">{{ $this->salesSummary['net_receipts'] ?? 'Rp 0' }}</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

