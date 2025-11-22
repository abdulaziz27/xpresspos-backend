<?php

namespace App\Filament\Admin\Resources\Plans\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\PlanFeature;

class PlanFeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'planFeatures';

    protected static ?string $modelLabel = 'Plan Feature';

    protected static ?string $pluralModelLabel = 'Plan Features';

    protected static ?string $title = 'Plan Features';

    protected static ?string $recordTitleAttribute = 'feature_code';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('feature_code')
                    ->label('Feature Code')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('MAX_STORES, MAX_PRODUCTS, ALLOW_LOYALTY')
                    ->helperText('Feature code (e.g., MAX_STORES, MAX_PRODUCTS, ALLOW_LOYALTY)'),

                Forms\Components\TextInput::make('limit_value')
                    ->label('Limit Value')
                    ->maxLength(255)
                    ->placeholder('10, -1, or empty for unlimited')
                    ->helperText('Limit value. Empty or null = unlimited'),

                Forms\Components\Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true)
                    ->helperText('Disable to turn off this feature'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('feature_code')
            ->columns([
                Tables\Columns\TextColumn::make('feature_code')
                    ->label('Feature Code')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('limit_value')
                    ->label('Limit')
                    ->formatStateUsing(fn ($state) => $state === null || $state === '-1' ? 'Unlimited' : $state)
                    ->badge()
                    ->color(fn ($state) => $state === null || $state === '-1' ? 'success' : 'info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_enabled')
                    ->label('Status')
                    ->options([
                        true => 'Enabled',
                        false => 'Disabled',
                    ]),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('feature_code', 'asc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

