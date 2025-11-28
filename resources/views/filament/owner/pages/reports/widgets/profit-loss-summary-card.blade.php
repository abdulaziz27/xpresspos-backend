<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Laporan Laba Rugi
        </x-slot>
        
        <div class="space-y-6">
            {{-- PENDAPATAN --}}
            <div class="space-y-3">
                <div class="gap-8 w-full" style="display: grid; grid-template-columns: 1fr 1fr;">
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">Pendapatan</span>
                    <span class="text-lg font-bold text-success-600 dark:text-success-400 text-right" style="font-weight: 700;">{{ \App\Support\Currency::rupiah($this->profitLossData['net_sales'] ?? 0) }}</span>
                </div>
            </div>

            {{-- COGS --}}
            <div class="space-y-3 border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="gap-8 w-full" style="display: grid; grid-template-columns: 1fr 1fr;">
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">COGS</span>
                    <span class="text-lg font-bold text-danger-600 dark:text-danger-400 text-right" style="font-weight: 700;">- {{ \App\Support\Currency::rupiah($this->profitLossData['total_cogs'] ?? 0) }}</span>
                </div>
            </div>

            {{-- LABA KOTOR --}}
            <div class="space-y-3 border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="gap-8 w-full" style="display: grid; grid-template-columns: 1fr 1fr;">
                    <span class="text-lg font-bold text-gray-900 dark:text-white">Laba Kotor</span>
                    <span class="text-xl font-bold text-success-600 dark:text-success-400 text-right" style="font-weight: 700;">{{ \App\Support\Currency::rupiah($this->profitLossData['gross_profit'] ?? 0) }}</span>
                </div>
            </div>

            {{-- BIAYA OPERASIONAL --}}
            <div class="space-y-3 border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="gap-8 w-full" style="display: grid; grid-template-columns: 1fr 1fr;">
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">Biaya Operasional</span>
                    <span class="text-lg font-bold text-danger-600 dark:text-danger-400 text-right" style="font-weight: 700;">- {{ \App\Support\Currency::rupiah($this->profitLossData['total_expenses'] ?? 0) }}</span>
                </div>
            </div>

            {{-- LABA BERSIH --}}
            <div class="space-y-3 border-t-2 border-gray-300 dark:border-gray-600 pt-4">
                <div class="gap-8 w-full" style="display: grid; grid-template-columns: 1fr 1fr;">
                    <span class="text-xl font-bold text-gray-900 dark:text-white">Laba Bersih</span>
                    <span class="text-2xl font-bold {{ ($this->profitLossData['net_profit'] ?? 0) >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }} text-right" style="font-weight: 700;">
                        {{ \App\Support\Currency::rupiah($this->profitLossData['net_profit'] ?? 0) }}
                    </span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

