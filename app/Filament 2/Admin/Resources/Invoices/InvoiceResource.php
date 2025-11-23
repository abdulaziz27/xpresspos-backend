<?php

namespace App\Filament\Admin\Resources\Invoices;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use App\Support\Currency;
use BackedEnum;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Plans & Subscriptions';

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Information')
                    ->description('Subscription invoice details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->copyable()
                                    ->weight('medium'),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        'refunded' => 'gray',
                                        'cancelled' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'paid' => 'Paid',
                                        'pending' => 'Pending',
                                        'failed' => 'Failed',
                                        'refunded' => 'Refunded',
                                        'cancelled' => 'Cancelled',
                                        default => ucfirst($state),
                                    }),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Amount')
                                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->amount ?? 0))),

                                Infolists\Components\TextEntry::make('tax_amount')
                                    ->label('Tax')
                                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->tax_amount ?? 0)))
                                    ->placeholder('No tax'),

                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('Total')
                                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->total_amount ?? 0)))
                                    ->weight('medium'),

                                Infolists\Components\TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date('d M Y')
                                    ->color(fn (Invoice $record): ?string => 
                                        $record->isOverdue() ? 'danger' : 
                                        ($record->isPending() && $record->due_date->diffInDays(now()) <= 7 ? 'warning' : null)
                                    ),

                                Infolists\Components\TextEntry::make('paid_at')
                                    ->label('Paid At')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Not paid'),
                            ]),
                    ]),

                Section::make('Tenant & Subscription Information')
                    ->description('Related tenant and subscription details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('subscription.tenant.name')
                                    ->label('Tenant')
                                    ->badge()
                                    ->color('info')
                                    ->weight('medium'),

                                Infolists\Components\TextEntry::make('subscription.tenant.email')
                                    ->label('Tenant Email')
                                    ->copyable()
                                    ->placeholder('N/A'),

                                Infolists\Components\TextEntry::make('subscription.plan.name')
                                    ->label('Plan')
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('subscription.billing_cycle')
                                    ->label('Billing Cycle')
                                    ->formatStateUsing(fn (?string $state): string => 
                                        $state === 'monthly' ? 'Monthly' : 
                                        ($state === 'yearly' ? 'Yearly' : ($state ? ucfirst($state) : 'N/A'))
                                    ),

                                Infolists\Components\TextEntry::make('subscription.starts_at')
                                    ->label('Subscription Starts')
                                    ->date('d M Y'),

                                Infolists\Components\TextEntry::make('subscription.ends_at')
                                    ->label('Subscription Ends')
                                    ->date('d M Y'),
                            ]),
                    ])
                    ->visible(fn ($record): bool => $record->subscription !== null)
                    ->collapsible(),

                Section::make('Line Items')
                    ->description('Invoice item details')
                    ->schema([
                        Infolists\Components\TextEntry::make('line_items_display')
                            ->label('Items')
                            ->getStateUsing(function ($record) {
                                $lineItems = $record->line_items ?? [];
                                
                                if (empty($lineItems) || !is_array($lineItems)) {
                                    return 'No items';
                                }
                                
                                $items = [];
                                foreach ($lineItems as $index => $item) {
                                    if (!is_array($item)) {
                                        continue;
                                    }
                                    
                                    $description = $item['description'] ?? 'Item';
                                    $quantity = $item['quantity'] ?? 1;
                                    
                                    // Convert to float, handling string values like "199000.00"
                                    $unitPrice = isset($item['unit_price']) 
                                        ? (float) $item['unit_price'] 
                                        : 0;
                                    $total = isset($item['total']) 
                                        ? (float) $item['total'] 
                                        : 0;
                                
                                    $items[] = sprintf(
                                        '%d. %s',
                                        $index + 1,
                                        $description
                                    ) . "\n   " . sprintf(
                                        'Qty: %s × %s = %s',
                                        $quantity,
                                        Currency::rupiah($unitPrice, false),
                                        Currency::rupiah($total, false)
                                    );
                                }
                                
                                return !empty($items) ? implode("\n\n", $items) : 'No items';
                            })
                            ->columnSpanFull()
                            ->listWithLineBreaks(),
                    ])
                    ->visible(fn ($record): bool => !empty($record->line_items) && is_array($record->line_items) && count($record->line_items) > 0)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Metadata')
                    ->description('Additional information')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata')
                            ->label('Metadata')
                            ->formatStateUsing(function ($state) {
                                if (empty($state) || !is_array($state)) {
                                    return 'No metadata';
                                }
                                
                                return collect($state)->map(function ($value, $key) {
                                    return sprintf('%s: %s', $key, is_array($value) ? json_encode($value) : $value);
                                })->join("\n");
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => !empty($record->metadata))
                    ->collapsible()
                    ->collapsed(),

                Section::make('Timeline')
                    ->description('Time information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d M Y H:i'),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('d M Y H:i'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Read-only
    }

    public static function canEdit($record): bool
    {
        return false; // Read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Read-only
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Eager load subscription with tenant and plan for better performance
        return parent::getEloquentQuery()
            ->with(['subscription.tenant', 'subscription.plan']);
    }
}

