<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Filter Laporan Kas Harian
        </x-slot>
        <x-slot name="description">
            Pilih tenant, cabang, dan periode data untuk laporan kas harian.
        </x-slot>
        {{ $this->form }}
        
        <div class="flex justify-end mt-4">
            <x-filament::button
                tag="a"
                href="{{ $this->getExportUrl() }}"
                target="_blank"
                color="success"
                outlined
                icon="heroicon-o-arrow-down-tray"
            >
                Export ke Excel
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

