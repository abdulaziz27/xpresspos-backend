<?php

namespace App\Filament\Admin\Resources\Stores\Pages;

use App\Filament\Admin\Resources\Stores\StoreResource;
use App\Models\Store;
use App\Services\PlanLimitService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        // Auto-generate code if not provided
        if (empty($data['code']) && !empty($data['name'])) {
            $baseCode = Str::slug($data['name']);
            if (strlen($baseCode) > 50) {
                $baseCode = substr($baseCode, 0, 50);
            }
            
            // Ensure uniqueness per tenant
            $tenantId = $data['tenant_id'] ?? null;
            $code = $baseCode;
            $counter = 1;
            
            while (Store::where('code', $code)
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->exists()) {
                $suffix = '-' . $counter;
                $maxLength = 50 - strlen($suffix);
                $code = substr($baseCode, 0, $maxLength) . $suffix;
                $counter++;
            }
            
            $data['code'] = $code;
        }
        
        return $data;
    }
}
