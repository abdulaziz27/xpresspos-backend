<?php

namespace App\Filament\Owner\Resources\CashSessions\RelationManagers;

use App\Support\Currency;
use App\Support\Money;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Pengeluaran Tunai';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('description')
                    ->label('Deskripsi')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('category')
                    ->label('Kategori')
                    ->options(self::getCategoryOptions())
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Jumlah')
                    ->prefix('Rp')
                    ->required()
                    ->rule('numeric|min:0.01')
                    ->dehydrateStateUsing(fn ($state) => Money::parseToDecimal($state)),
                Forms\Components\DatePicker::make('expense_date')
                    ->label('Tanggal Pengeluaran')
                    ->default(now())
                    ->required(),
                Forms\Components\TextInput::make('receipt_number')
                    ->label('No. Kwitansi')
                    ->maxLength(255),
                Forms\Components\TextInput::make('vendor')
                    ->label('Vendor')
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->maxLength(1000),
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::getCategoryOptions()[$state] ?? ucfirst(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->amount ?? 0)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dicatat Oleh')
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('expense_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Pengeluaran'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function getCategoryOptions(): array
    {
        return [
            'office_supplies' => 'ATK',
            'utilities' => 'Utilitas',
            'rent' => 'Sewa',
            'marketing' => 'Marketing',
            'equipment' => 'Peralatan',
            'maintenance' => 'Perawatan',
            'travel' => 'Perjalanan',
            'food' => 'Makanan & Minuman',
            'other' => 'Lainnya',
        ];
    }
}

