@php
    use Illuminate\Support\Str;
@endphp

<x-filament-panels::page>
    <div class="mx-auto mb-8 flex max-w-5xl flex-col-reverse gap-6 lg:flex-row lg:items-start">
        <div class="flex-1 text-sm text-gray-600 dark:text-gray-300">
            <p class="font-medium text-gray-800 dark:text-gray-100">Pilih Toko Aktif</p>
            <p class="mt-1 leading-relaxed">Mengganti toko akan memengaruhi data yang ditampilkan di dashboard, laporan, dan pengaturan lainnya. Pilih toko yang ingin Anda kelola saat ini.</p>
        </div>

        <div class="flex h-full flex-1 items-center justify-center">
            <div class="w-full max-w-[420px] rounded-2xl bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 p-[1px] shadow-lg dark:from-primary-400 dark:via-primary-500 dark:to-primary-400">
                <div class="space-y-4 rounded-2xl bg-white px-6 py-7 text-gray-600 shadow-inner dark:bg-gray-900 dark:text-gray-200">
                    <div class="flex items-center gap-3 text-primary-600 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-command-line" class="h-5 w-5" />
                        <p class="text-sm font-semibold uppercase tracking-wide">Multi Store Overview</p>
                    </div>

                    <div class="space-y-3 text-sm">
                        <p class="leading-relaxed">Anda memiliki akses ke <span class="font-semibold text-gray-900 dark:text-gray-100">{{ count($stores) }}</span> toko dalam akun ini. Gunakan panel ini untuk berpindah toko dengan cepat.</p>
                        <p class="leading-relaxed text-gray-500 dark:text-gray-400">Toko aktif saat ini menentukan data yang ditampilkan di:</p>
                        <ul class="space-y-2 text-gray-500 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <x-filament::icon icon="heroicon-o-chart-bar" class="mt-[2px] h-4 w-4" />
                                Dashboard & laporan penjualan
                            </li>
                            <li class="flex items-start gap-2">
                                <x-filament::icon icon="heroicon-o-document-text" class="mt-[2px] h-4 w-4" />
                                Pengaturan nota, pajak, dan katalog
                            </li>
                            <li class="flex items-start gap-2">
                                <x-filament::icon icon="heroicon-o-user-group" class="mt-[2px] h-4 w-4" />
                                Manajemen staf dan pelanggan
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 text-xs text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-300">
                        <p>Tips: Jika Anda mengelola banyak cabang, catat kode toko pada nama agar mudah diidentifikasi saat berpindah.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto grid max-w-5xl gap-6 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($stores as $store)
            <x-filament::card @class(['border-primary-200 ring-2 ring-primary-200/60 dark:border-primary-500/70 dark:ring-primary-500/40' => $store['is_active']])>
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-base font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-200">
                        {{ Str::of($store['name'])->substr(0, 2)->upper() }}
                    </div>

                    <div class="min-w-0 flex-1 space-y-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $store['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Store ID: {{ $store['id'] }}</p>
                        </div>

                        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <p>{{ $store['email'] ?? 'Tidak ada email terdaftar' }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge
                                    @class(['bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300' => $store['status'] === 'active',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-300' => $store['status'] === 'inactive',
                                            'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300' => $store['status'] === 'suspended'])
                                >
                                    {{ ucfirst($store['status']) }}
                                </x-filament::badge>

                                @if($store['is_active'])
                                    <x-filament::badge color="primary" icon="heroicon-o-check-circle">
                                        Toko Aktif
                                    </x-filament::badge>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end">
                    @if(!$store['is_active'])
                        <x-filament::button
                            size="sm"
                            wire:click="selectStore('{{ $store['id'] }}')"
                            icon="heroicon-o-arrow-right-circle"
                        >
                            Gunakan Toko Ini
                        </x-filament::button>
                    @else
                        <x-filament::button size="sm" color="gray" disabled icon="heroicon-o-check-circle">
                            Aktif Saat Ini
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::card>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                Anda belum memiliki akses ke toko mana pun.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
