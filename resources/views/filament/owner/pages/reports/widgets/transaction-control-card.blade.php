<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Kontrol Transaksi
        </x-slot>
        
        <div class="space-y-4">
            <div class="space-y-3">
                <div class="flex justify-between items-center gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Total Transaksi</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->transactionControl['total_transactions'] ?? '0' }}</span>
                </div>
                
                <div class="flex justify-between items-center gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Rata-rata Transaksi</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->transactionControl['avg_transaction'] ?? 'Rp 0' }}</span>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Status Transaksi</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Berhasil</span>
                        <span class="text-sm font-medium text-success-600 dark:text-success-400 text-right flex-shrink-0">{{ $this->transactionControl['successful_transactions'] ?? '0' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Gagal</span>
                        <span class="text-sm font-medium text-danger-600 dark:text-danger-400 text-right flex-shrink-0">{{ $this->transactionControl['failed_transactions'] ?? '0' }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                <div class="flex justify-between items-center gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Total Item Terjual</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->transactionControl['total_items_sold'] ?? '0' }}</span>
                </div>
                <div class="flex justify-between items-center gap-8">
                    <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Rata-rata Item/Transaksi</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->transactionControl['avg_items_per_transaction'] ?? '0' }}</span>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Pembatalan</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Jumlah Pembatalan</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->transactionControl['cancelled_count'] ?? '0' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Total Pembatalan</span>
                        <span class="text-sm font-medium text-danger-600 dark:text-danger-400 text-right flex-shrink-0">{{ $this->transactionControl['cancelled_total'] ?? 'Rp 0' }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Refund</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Refund Tunai</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->transactionControl['cash_refunds'] ?? 'Rp 0' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">Refund Non Tunai</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white text-right flex-shrink-0">{{ $this->transactionControl['non_cash_refunds'] ?? 'Rp 0' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-8 border-t border-gray-200 dark:border-gray-700 pt-2 mt-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-shrink-0">Total Refund</span>
                        <span class="text-sm font-semibold text-danger-600 dark:text-danger-400 text-right flex-shrink-0">{{ $this->transactionControl['total_refunds'] ?? 'Rp 0' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

