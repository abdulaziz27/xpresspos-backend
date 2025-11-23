<?php

namespace App\Filament\Admin\Resources\Stores\Pages;

use App\Filament\Admin\Resources\Stores\StoreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Admin tidak bisa delete store
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Flatten settings array to form fields
        if (isset($data['settings']) && is_array($data['settings'])) {
            $settings = $data['settings'];
            
            // Extract custom settings if exists
            $customSettings = [];
            $knownSettings = [
                'tax_rate',
                'service_charge_rate',
                'tax_included',
                'website_url',
                'wifi_name',
                'wifi_password',
                'thank_you_message',
                'receipt_footer',
            ];
            
            foreach ($settings as $key => $value) {
                if (!in_array($key, $knownSettings) && $key !== 'custom') {
                    $customSettings[$key] = $value;
                }
            }
            
            // Flatten known settings
            foreach ($knownSettings as $key) {
                if (isset($settings[$key])) {
                    $data["settings.{$key}"] = $settings[$key];
                }
            }
            
            // Set custom settings
            if (!empty($customSettings)) {
                $data['settings.custom'] = $customSettings;
            }
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
                    // Only set if value is not null/empty or is explicitly set
                    if ($value !== null && $value !== '') {
                        $settings[$settingKey] = $value;
                    }
                }
                
                // Remove from data to avoid duplicate
                unset($data[$key]);
            }
        }
        
        // Merge with existing settings to preserve any other settings
        $existingSettings = $this->record->settings ?? [];
        if (!is_array($existingSettings)) {
            $existingSettings = [];
        }
        
        // Merge settings, with form data taking precedence
        $data['settings'] = array_merge($existingSettings, $settings);
        
        // Ensure status is properly set (enum value)
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'suspended'])) {
            // If invalid status, default to active
            $data['status'] = 'active';
        }
        
        return $data;
    }
}
