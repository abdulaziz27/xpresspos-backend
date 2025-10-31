<?php

namespace App\Filament\Owner\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class OwnerDashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int | array
    {
        // Define responsive grid so widget $columnSpan works (e.g., ['xl' => 6])
        return [
            'default' => 1,
            'md' => 1,
            'lg' => 12,
            'xl' => 12,
        ];
    }
}


