<?php

namespace App\Filament\Owner\Resources\Products\RelationManagers;

use App\Models\ModifierGroup;
use Filament\Actions;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ModifierGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'modifierGroups';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Modifier';

    protected static ?string $modelLabel = 'modifier';

    protected static ?string $pluralModelLabel = 'modifier';

    public function form(Schema $schema): Schema
    {
        // For belongsToMany, form is handled by AttachAction
        // This form is for editing pivot data
        return $schema
            ->schema([
                Forms\Components\Toggle::make('is_required')
                    ->label('Wajib Dipilih')
                    ->helperText('Pelanggan harus memilih minimal 1 item dari modifier ini'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan Tampil')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->helperText('Angka kecil akan tampil lebih dulu (1, 2, 3...)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Modifier')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('items')
                    ->badge()
                    ->color('info')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.is_required')
                    ->label('Wajib Dipilih')
                    ->badge()
                    ->color(fn($state) => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn($state) => $state ? 'Ya' : 'Tidak')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('pivot.sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(false)
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Ditambahkan')
                    ->dateTime()
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua modifier')
                    ->trueLabel('Hanya aktif')
                    ->falseLabel('Hanya nonaktif'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Attach')
                    ->color('primary')
                    ->form(function (AttachAction $action): array {
                        $product = $this->getOwnerRecord();
                        $tenantId = $product->tenant_id;
                        
                        // Exclude already attached groups
                        $attachedGroupIds = $product->modifierGroups()->pluck('modifier_groups.id')->toArray();
                        
                        return [
                            Forms\Components\Select::make('recordId')
                                ->label('Modifier Group')
                                ->options(function () use ($tenantId, $attachedGroupIds) {
                                    return ModifierGroup::query()
                                        ->where('tenant_id', $tenantId)
                                        ->where('is_active', true)
                                        ->whereNotIn('id', $attachedGroupIds)
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\Toggle::make('is_required')
                                ->label('Wajib Dipilih')
                                ->default(false)
                                ->helperText('Pelanggan harus memilih minimal 1 item dari modifier ini'),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Urutan Tampil')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('Angka kecil akan tampil lebih dulu (1, 2, 3...)'),
                        ];
                    })
                    ->using(function (array $data): void {
                        $product = $this->getOwnerRecord();
                        $recordId = $data['recordId'] ?? null;
                        
                        if (!$recordId) {
                            throw new \Exception('Modifier group tidak dipilih');
                        }
                        
                        $pivotData = [
                            'is_required' => $data['is_required'] ?? false,
                            'sort_order' => $data['sort_order'] ?? 0,
                        ];
                        
                        $product->modifierGroups()->attach($recordId, $pivotData);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Forms\Components\Toggle::make('is_required')
                            ->label('Wajib Dipilih')
                            ->helperText('Pelanggan harus memilih minimal 1 item dari modifier ini'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Angka kecil akan tampil lebih dulu (1, 2, 3...)'),
                    ]),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ])
            // Note: Sorting by pivot.sort_order is handled automatically by 
            // Product::modifierGroups() relationship which has ->orderByPivot('sort_order')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

