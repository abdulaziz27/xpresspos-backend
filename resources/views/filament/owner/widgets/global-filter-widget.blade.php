<x-filament-widgets::widget>
    <x-filament::section
        :heading="'📊 Filter Global Dashboard'"
        :description="'Pilih cabang dan periode untuk melihat data yang sesuai. Semua widget akan otomatis ter-update.'">
        
        <x-slot name="headerEnd">
            <x-filament::button
                wire:click="resetFilters"
                color="gray"
                size="sm"
                icon="heroicon-o-arrow-path">
                Reset
            </x-filament::button>
        </x-slot>

        <div class="space-y-6">
            <!-- Filter Form -->
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Store Filter -->
                <x-filament::input.wrapper>
                    <label for="store-filter" class="fi-input-label block text-sm font-medium leading-6 text-gray-950 dark:text-white">
                        Cabang
                    </label>
                    <x-filament::input.select
                        wire:model.live="selectedStore"
                        id="store-filter">
                        @foreach($stores as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <!-- Date Preset Filter -->
                <x-filament::input.wrapper>
                    <label for="preset-filter" class="fi-input-label block text-sm font-medium leading-6 text-gray-950 dark:text-white">
                        Periode
                    </label>
                    <x-filament::input.select
                        wire:model.live="selectedPreset"
                        id="preset-filter">
                        @foreach($presets as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <!-- Placeholder for alignment -->
                @if($selectedPreset !== 'custom')
                    <div></div>
                @endif

                <!-- Custom Date Range (visible when custom selected) -->
                @if($selectedPreset === 'custom')
                    <div class="md:col-span-2 grid gap-4 md:grid-cols-2">
                        <x-filament::input.wrapper>
                            <label for="date-start" class="fi-input-label block text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                Dari Tanggal
                            </label>
                            <x-filament::input
                                type="date"
                                wire:model.blur="dateStart"
                                id="date-start" />
                        </x-filament::input.wrapper>
                        
                        <x-filament::input.wrapper>
                            <label for="date-end" class="fi-input-label block text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                Sampai Tanggal
                            </label>
                            <x-filament::input
                                type="date"
                                wire:model.blur="dateEnd"
                                id="date-end" />
                        </x-filament::input.wrapper>
                    </div>
                @endif
            </div>

            <!-- Current Filter Summary -->
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge color="primary" icon="heroicon-o-building-storefront">
                    {{ $summary['store'] }}
                </x-filament::badge>
                
                <x-filament::badge color="success" icon="heroicon-o-calendar">
                    {{ $summary['date_start'] }} - {{ $summary['date_end'] }}
                </x-filament::badge>

                <x-filament::badge color="info" icon="heroicon-o-clock">
                    {{ $summary['date_preset_label'] }}
                </x-filament::badge>
            </div>

            <!-- Pro Tip -->
            <div class="rounded-lg border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-900/10 p-4">
                <div class="flex items-start gap-3">
                    <x-filament::icon
                        icon="heroicon-o-light-bulb"
                        class="h-5 w-5 text-warning-600 dark:text-warning-400 mt-0.5 flex-shrink-0"
                    />
                    <div class="text-sm text-warning-700 dark:text-warning-300">
                        <strong>Pro Tip:</strong> Pilih "Semua Cabang" untuk melihat performa seluruh bisnis Anda. Filter otomatis tersimpan dalam sesi.
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
