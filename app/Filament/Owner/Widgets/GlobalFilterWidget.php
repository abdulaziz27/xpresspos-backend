<?php

namespace App\Filament\Owner\Widgets;

use App\Services\GlobalFilterService;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class GlobalFilterWidget extends Widget
{
    protected static ?int $sort = -9999; // Show at top
    
    protected int | string | array $columnSpan = 'full';
    
    protected string $view = 'filament.owner.widgets.global-filter-widget';

    public string $selectedStore = 'all';
    public string $selectedPreset = 'this_month';
    public ?string $dateStart = null;
    public ?string $dateEnd = null;
    public array $stores = [];
    public array $presets = [];
    public array $summary = [];

    public function mount(): void
    {
        $globalFilter = app(GlobalFilterService::class);
        
        // Load initial data
        $this->stores = $this->getStoreOptions();
        $this->presets = $globalFilter->getAvailableDatePresets();
        $this->summary = $globalFilter->getFilterSummary();
        
        // Set initial values
        $this->selectedStore = $this->summary['store_id'] ?? 'all';
        $this->selectedPreset = $this->summary['date_preset'] ?? 'this_month';
        
        $dateRange = $globalFilter->getCurrentDateRange();
        $this->dateStart = $dateRange['start']->toDateString();
        $this->dateEnd = $dateRange['end']->toDateString();
    }

    public function updatedSelectedStore($value): void
    {
        $globalFilter = app(GlobalFilterService::class);
        $globalFilter->setStore($value === 'all' ? null : $value);
        
        $this->refreshData();
        $this->dispatch('filter-updated');
    }

    public function updatedSelectedPreset($value): void
    {
        if ($value !== 'custom') {
            $globalFilter = app(GlobalFilterService::class);
            $globalFilter->setDatePreset($value);
            
            $dateRange = $globalFilter->getCurrentDateRange();
            $this->dateStart = $dateRange['start']->toDateString();
            $this->dateEnd = $dateRange['end']->toDateString();
            
            $this->refreshData();
            $this->dispatch('filter-updated');
        }
    }

    public function updatedDateStart($value): void
    {
        if ($this->selectedPreset === 'custom' && $value && $this->dateEnd) {
            $this->applyCustomDateRange();
        }
    }

    public function updatedDateEnd($value): void
    {
        if ($this->selectedPreset === 'custom' && $value && $this->dateStart) {
            $this->applyCustomDateRange();
        }
    }

    protected function applyCustomDateRange(): void
    {
        $globalFilter = app(GlobalFilterService::class);
        $globalFilter->setDateRange(
            \Carbon\Carbon::parse($this->dateStart),
            \Carbon\Carbon::parse($this->dateEnd),
            'custom'
        );
        
        $this->refreshData();
        $this->dispatch('filter-updated');
    }

    public function resetFilters(): void
    {
        $globalFilter = app(GlobalFilterService::class);
        $globalFilter->reset();
        
        $this->mount(); // Reload initial state
        $this->dispatch('filter-updated');
    }

    protected function refreshData(): void
    {
        $globalFilter = app(GlobalFilterService::class);
        $this->summary = $globalFilter->getFilterSummary();
    }

    protected function getStoreOptions(): array
    {
        $globalFilter = app(GlobalFilterService::class);
        $stores = $globalFilter->getAvailableStores();
        
        $options = ['all' => 'Semua Cabang'];
        foreach ($stores as $store) {
            $options[$store->id] = $store->name;
        }
        
        return $options;
    }

    #[On('filter-updated')]
    public function refreshWidget(): void
    {
        // Trigger page reload for full refresh
        $this->js('setTimeout(() => window.location.reload(), 300)');
    }

    public static function canView(): bool
    {
        return true;
    }
}
