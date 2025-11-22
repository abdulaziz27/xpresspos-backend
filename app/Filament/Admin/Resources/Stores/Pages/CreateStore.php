<?php

namespace App\Filament\Admin\Resources\Stores\Pages;

use App\Filament\Admin\Resources\Stores\StoreResource;
use App\Models\Store;
use App\Services\PlanLimitService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Admin can select tenant_id directly from form
        // But we should still check plan limits if tenant is selected
        if (isset($data['tenant_id']) && $data['tenant_id']) {
            $tenant = \App\Models\Tenant::find($data['tenant_id']);
            
            if ($tenant) {
                $planLimitService = app(PlanLimitService::class);

                // Check MAX_STORES limit before creating
                $currentStoreCount = Store::where('tenant_id', $tenant->id)->count();
                $canPerform = $planLimitService->canPerformAction($tenant, 'create_store', $currentStoreCount);

                if (!$canPerform['allowed']) {
                    Notification::make()
                        ->title('Limit tercapai')
                        ->body($canPerform['message'] ?? 'Tenant ini telah mencapai batas maksimum store untuk plan mereka.')
                        ->danger()
                        ->send();

                    $this->halt();
                }
            }
        }

        // Merge form fields back into settings array
        $settings = [];
        
        // Collect all settings.* fields
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'settings.')) {
                $settingKey = str_replace('settings.', '', $key);
                
                // Handle custom settings separately
                if ($settingKey === 'custom' && is_array($value)) {
                    $settings = array_merge($settings, $value);
                } else {
                    $settings[$settingKey] = $value;
                }
                
                // Remove from data to avoid duplicate
                unset($data[$key]);
            }
        }
        
        // Set settings if any
        if (!empty($settings)) {
            $data['settings'] = $settings;
        }
        
        return $data;
    }
}
