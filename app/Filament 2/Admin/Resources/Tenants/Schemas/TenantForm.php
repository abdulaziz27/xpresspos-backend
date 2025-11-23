<?php

namespace App\Filament\Admin\Resources\Tenants\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Information')
                    ->description('Main tenant data as XpressPOS customer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Business Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Company or business name'),

                                TextInput::make('email')
                                    ->label('Contact Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('email@example.com'),
                            ]),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+62...'),
                    ])
                    ->columns(1),

                Section::make('Status & Subscription')
                    ->description('Tenant status and subscription information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'trial' => 'Trial',
                                        'active' => 'Active',
                                        'suspended' => 'Suspended',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('trial')
                                    ->required()
                                    ->native(false),

                                TextInput::make('plan_display')
                                    ->label('Active Plan')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(function (?Model $record) {
                                        if ($record) {
                                            $subscription = $record->activeSubscription();
                                            return $subscription?->plan?->name ?? 'N/A';
                                        }
                                        return 'N/A';
                                    })
                                    ->helperText('Plan is determined from active subscription. Manage subscription in "Subscriptions" tab.'),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }
}

