<?php

namespace App\Filament\Owner\Resources\Suppliers;

use App\Filament\Owner\Resources\Suppliers\Pages;
use App\Filament\Owner\Resources\Suppliers\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Traits\HasPlanBasedNavigation;
use App\Models\Supplier;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Gate;

class SupplierResource extends Resource
{
    use HasPlanBasedNavigation;
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Supplier';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 50; // 5. Supplier

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Supplier')
                    ->description('Data dasar supplier/pemasok')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Supplier')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Contoh: PT ABC Supplier'),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Aktif',
                                        'inactive' => 'Tidak Aktif',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('supplier@example.com'),
                                TextInput::make('phone')
                                    ->label('Telepon')
                                    ->tel()
                                    ->maxLength(50)
                                    ->placeholder('081234567890'),
                            ]),
                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Alamat lengkap supplier'),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('tax_id')
                                    ->label('NPWP/Tax ID')
                                    ->maxLength(100)
                                    ->placeholder('01.234.567.8-901.000'),
                                TextInput::make('bank_account')
                                    ->label('Rekening Bank')
                                    ->maxLength(150)
                                    ->placeholder('Bank: No. Rekening'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Supplier')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-envelope')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-phone')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_id')
                    ->label('NPWP/Tax ID')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('name', 'asc');
            })
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasPlanFeature('ALLOW_INVENTORY');
    }

    public static function canViewAny(): bool
    {
        if (! static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    /**
     * Owner can create suppliers.
     */
    public static function canCreate(): bool
    {
        if (! static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('create', static::$model);
    }

    /**
     * Owner can edit suppliers.
     */
    public static function canEdit(Model $record): bool
    {
        if (! static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('update', $record);
    }

    /**
     * Owner can delete suppliers.
     * FK constraints will prevent deletion if supplier is used in purchase orders.
     */
    public static function canDelete(Model $record): bool
    {
        if (! static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('delete', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            PurchaseOrdersRelationManager::class,
        ];
    }
}

