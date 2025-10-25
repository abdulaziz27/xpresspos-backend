<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use App\Services\StoreContext;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Subscription';



    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Subscription Details')
                    ->schema([
                        Forms\Components\Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'cancelled' => 'Cancelled',
                                'expired' => 'Expired',
                            ])
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\Select::make('billing_cycle')
                            ->options([
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('starts_at')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('ends_at')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('trial_ends_at')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Billing')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Started')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (Subscription $record): string => 
                        $record->ends_at->isPast() ? 'danger' : 
                        ($record->ends_at->diffInDays() <= 7 ? 'warning' : 'success')
                    ),
                
                Tables\Columns\TextColumn::make('days_until_expiration')
                    ->label('Days Left')
                    ->getStateUsing(fn (Subscription $record): string => 
                        $record->ends_at->isPast() ? 'Expired' : 
                        $record->ends_at->diffInDays() . ' days'
                    )
                    ->color(fn (Subscription $record): string => 
                        $record->ends_at->isPast() ? 'danger' : 
                        ($record->ends_at->diffInDays() <= 7 ? 'warning' : 'success')
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                    ]),
                
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription Overview')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('plan.name')
                                    ->label('Plan'),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'suspended' => 'warning',
                                        'cancelled' => 'danger',
                                        'expired' => 'gray',
                                        default => 'gray',
                                    }),
                                
                                Infolists\Components\TextEntry::make('billing_cycle')
                                    ->label('Billing Cycle')
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                
                                Infolists\Components\TextEntry::make('amount')
                                    ->money('IDR'),
                                
                                Infolists\Components\TextEntry::make('starts_at')
                                    ->label('Started')
                                    ->date(),
                                
                                Infolists\Components\TextEntry::make('ends_at')
                                    ->label('Expires')
                                    ->date()
                                    ->color(fn (Subscription $record): string => 
                                        $record->ends_at->isPast() ? 'danger' : 
                                        ($record->ends_at->diffInDays() <= 7 ? 'warning' : 'success')
                                    ),
                            ]),
                    ]),
                
                Section::make('Payment History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('subscriptionPayments')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('amount')
                                            ->money('IDR'),
                                        
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'paid' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                'expired' => 'gray',
                                                default => 'gray',
                                            }),
                                        
                                        Infolists\Components\TextEntry::make('payment_method')
                                            ->label('Method')
                                            ->formatStateUsing(fn ($record): string => 
                                                $record->getPaymentMethodDisplayName()
                                            ),
                                        
                                        Infolists\Components\TextEntry::make('paid_at')
                                            ->label('Paid At')
                                            ->dateTime()
                                            ->placeholder('Not paid'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $storeContext = app(StoreContext::class);
        
        return parent::getEloquentQuery()
            ->where('store_id', $storeContext->current(auth()->user()))
            ->with(['plan', 'subscriptionPayments']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $storeContext = app(StoreContext::class);
        
        $activeCount = static::getEloquentQuery()
            ->where('status', 'active')
            ->count();
            
        return $activeCount > 0 ? (string) $activeCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}