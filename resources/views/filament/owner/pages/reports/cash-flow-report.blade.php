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

        <div class="grid gap-4 md:grid-cols-3">
            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pembayaran Selesai</p>
                <p class="mt-2 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $cashSummary['total_payments'] ?? 'Rp 0' }}</p>
            </x-filament::section>

            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Net Cash</p>
                <p class="mt-2 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $cashSummary['net_cash'] ?? 'Rp 0' }}</p>
            </x-filament::section>

            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Rata-Rata Tiket</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ $cashSummary['average_ticket'] ?? 'Rp 0' }}</p>
            </x-filament::section>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Refund Diproses</p>
                <p class="mt-2 text-2xl font-semibold text-orange-600 dark:text-orange-400">{{ $refundSummary['processed'] ?? 'Rp 0' }}</p>
            </x-filament::section>

            <x-filament::section class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Refund Pending</p>
                <p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ $refundSummary['pending'] ?? 'Rp 0' }}</p>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>


