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

        <x-filament::section>
            <x-slot name="heading">Produk dengan Margin Terbaik</x-slot>
            <div class="overflow-hidden rounded-xl border border-gray-100 dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Produk</th>
                            <th class="px-4 py-2 text-right font-medium">Terjual</th>
                            <th class="px-4 py-2 text-right font-medium">Revenue</th>
                            <th class="px-4 py-2 text-right font-medium">COGS</th>
                            <th class="px-4 py-2 text-right font-medium">Profit</th>
                            <th class="px-4 py-2 text-right font-medium">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($profitAnalysis as $row)
                            <tr class="text-gray-900 dark:text-gray-100">
                                <td class="px-4 py-2">{{ $row['product_name'] }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($row['quantity_sold']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['revenue'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['cost'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['profit'] }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($row['margin_percent'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada data penjualan pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>


