<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Ringkasan Kas
        </x-slot>
        
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            {{-- Kas Masuk --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Kas Masuk</p>
                    <p class="text-xl font-bold text-success-600 dark:text-success-400">{{ $this->summary['cash_in'] }}</p>
                </div>
            </div>

            {{-- Refund Tunai --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Refund Tunai</p>
                    <p class="text-xl font-bold text-danger-600 dark:text-danger-400">{{ $this->summary['cash_refund'] }}</p>
                </div>
            </div>

            {{-- Pengeluaran Tunai --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pengeluaran Tunai</p>
                    <p class="text-xl font-bold text-warning-600 dark:text-warning-400">{{ $this->summary['expenses'] }}</p>
                </div>
            </div>

            {{-- Kas Bersih --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Kas Bersih</p>
                    <p class="text-xl font-bold {{ $this->summary['net_cash_raw'] >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $this->summary['net_cash'] }}
                    </p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

