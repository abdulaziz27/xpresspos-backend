<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Filter Laporan Penjualan
        </x-slot>
        <x-slot name="description">
            Pilih tenant, cabang, dan periode data untuk seluruh widget di halaman ini.
        </x-slot>
        {{ $this->form }}
    </x-filament::section>
</x-filament-widgets::widget>

