<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Filter Laporan Penjualan
        </x-slot>
        <x-slot name="description">
            Pilih tenant, cabang, dan periode data untuk seluruh widget di halaman ini.
        </x-slot>
        {{ $this->form }}
        
        {{-- <div class="flex justify-end" style="margin-top: 2rem;">
            <a 
                href="{{ $this->getExportUrl() }}" 
                target="_blank"
                class="inline-flex items-center px-3 py-1.5 text-sm bg-success-600 text-white rounded-md hover:bg-success-700 transition-colors"
            >
                Export ke Excel
            </a>
        </div> --}}
    </x-filament::section>
</x-filament-widgets::widget>

