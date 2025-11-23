<?php

namespace App\Filament\Owner\Widgets;

use Filament\Widgets\Widget;

class FeatureLockWidget extends Widget
{
    protected string $view = 'filament.owner.widgets.feature-lock';
    
    public string $feature;
    public string $title;
    public string $description;
    public string $requiredPlan = 'Pro';

    public function isVisible(): bool
    {
        return !auth()->user()->hasFeature($this->feature);
    }
}
