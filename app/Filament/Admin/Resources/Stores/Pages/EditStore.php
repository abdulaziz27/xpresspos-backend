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
            DeleteAction::make(),
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
                    $settings[$settingKey] = $value;
                }
                
                // Remove from data to avoid duplicate
                unset($data[$key]);
            }
        }
        
        // Merge with existing settings to preserve any other settings
        $existingSettings = $this->record->settings ?? [];
        $data['settings'] = array_merge($existingSettings, $settings);
        
        return $data;
    }
}
