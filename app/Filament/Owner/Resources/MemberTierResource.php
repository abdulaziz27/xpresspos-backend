<?php

namespace App\Filament\Owner\Resources;

use Filament\Resources\Resource;

class MemberTierResource extends Resource
{
    public static function canViewAny(): bool
    {
        return true;
    }
}
