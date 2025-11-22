<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Filter Laporan Kas Harian
        </x-slot>
        <x-slot name="description">
            Pilih tenant, cabang, dan periode data untuk laporan kas harian.
        </x-slot>
        {{ $this->form }}
    </x-filament::section>
</x-filament-widgets::widget>

