<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Filter Laporan Bahan Baku
        </x-slot>
        <x-slot name="description">
            Pilih tenant, cabang, dan periode data untuk laporan bahan baku.
        </x-slot>
        {{ $this->form }}
    </x-filament::section>
</x-filament-widgets::widget>

