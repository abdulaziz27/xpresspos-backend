<?php

namespace App\Filament\Owner\Resources\TenantAddOns\Pages;

use App\Filament\Owner\Resources\TenantAddOns\TenantAddOnResource;
use App\Models\AddOn;
use App\Models\AddOnPayment;
use App\Models\TenantAddOn;
use App\Services\GlobalFilterService;
use App\Services\XenditService;
use App\Support\Currency;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Components\Utilities\Set as SchemaSet;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateTenantAddOn extends CreateRecord
{
    protected static string $resource = TenantAddOnResource::class;

    protected ?string $paymentUrl = null;

    public function form(Schema $schema): Schema
    {
        $tenant = auth()->user()?->currentTenant();
        if (!$tenant) {
            return $schema;
        }

        $availableAddOns = AddOn::active()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function ($addOn) {
                return [$addOn->id => $addOn->name . ' - ' . Currency::rupiah($addOn->price_monthly) . '/bulan'];
            });

        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Pilih Add-on')
                    ->description('Pilih add-on yang ingin Anda beli')
                    ->schema([
                        Forms\Components\Select::make('add_on_id')
                            ->label('Add-on')
                            ->options($availableAddOns)
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, SchemaSet $set) {
                                if ($state) {
                                    $addOn = AddOn::find($state);
                                    if ($addOn) {
                                        $set('billing_cycle', 'monthly');
                                        $set('price', $addOn->price_monthly);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Jumlah Unit')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Jumlah unit add-on yang ingin dibeli'),

                        Forms\Components\Select::make('billing_cycle')
                            ->label('Siklus Penagihan')
                            ->options([
                                'monthly' => 'Bulanan',
                                'annual' => 'Tahunan',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, SchemaGet $get, SchemaSet $set) {
                                $addOnId = $get('add_on_id');
                                if ($addOnId) {
                                    $addOn = AddOn::find($addOnId);
                                    if ($addOn) {
                                        $set('price', $addOn->getPrice($state));
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('price')
                            ->label('Total Harga')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->helperText(function (SchemaGet $get) {
                                $addOnId = $get('add_on_id');
                                $quantity = $get('quantity') ?? 1;
                                $billingCycle = $get('billing_cycle') ?? 'monthly';
                                
                                if ($addOnId) {
                                    $addOn = AddOn::find($addOnId);
                                    if ($addOn) {
                                        $unitPrice = $addOn->getPrice($billingCycle);
                                        $total = $unitPrice * $quantity;
                                        return "Harga per unit: " . Currency::rupiah($unitPrice) . " × {$quantity} = " . Currency::rupiah($total);
                                    }
                                }
                                return '';
                            }),
                    ]),

                \Filament\Schemas\Components\Section::make('Informasi Add-on')
                    ->schema([
                        Forms\Components\TextInput::make('add_on_name')
                            ->label('Nama')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (SchemaGet $get) => !empty($get('add_on_id')))
                            ->default(fn (SchemaGet $get) => AddOn::find($get('add_on_id'))?->name),

                        Forms\Components\Textarea::make('add_on_description')
                            ->label('Deskripsi')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(2)
                            ->visible(fn (SchemaGet $get) => !empty($get('add_on_id')))
                            ->default(fn (SchemaGet $get) => AddOn::find($get('add_on_id'))?->description),

                        Forms\Components\TextInput::make('add_on_bonus')
                            ->label('Bonus Limit')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (SchemaGet $get) => !empty($get('add_on_id')))
                            ->default(function (SchemaGet $get) {
                                $addOn = AddOn::find($get('add_on_id'));
                                if (!$addOn) {
                                    return '';
                                }
                                return '+' . number_format($addOn->quantity) . ' ' . $this->getFeatureLabel($addOn->feature_code);
                            }),
                    ])
                    ->visible(fn (SchemaGet $get) => !empty($get('add_on_id')))
                    ->columns(1),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = auth()->user()?->currentTenant();
        if (!$tenant) {
            throw new \Exception('Tenant tidak ditemukan');
        }

        $addOn = AddOn::find($data['add_on_id']);
        if (!$addOn) {
            throw new \Exception('Add-on tidak ditemukan');
        }

        $data['tenant_id'] = $tenant->id;
        $data['price'] = $addOn->getPrice($data['billing_cycle']) * ($data['quantity'] ?? 1);
        $data['status'] = 'pending'; // Set to pending until payment is confirmed
        $data['starts_at'] = now();

        // Set ends_at based on billing cycle
        if ($data['billing_cycle'] === 'monthly') {
            // Monthly billing: ends_at is null (recurring)
            $data['ends_at'] = null;
        } else {
            // Annual billing: ends_at is 1 year from now
            $data['ends_at'] = now()->addYear();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            DB::beginTransaction();

            $tenant = auth()->user()?->currentTenant();
            $user = auth()->user();
            $addOn = AddOn::find($this->record->add_on_id);

            if (!$tenant || !$user || !$addOn) {
                throw new \Exception('Data tidak lengkap untuk membuat payment');
            }

            // Create Xendit invoice
            $xenditService = app(XenditService::class);
            
            $externalId = 'XPOS-ADDON-' . $this->record->id . '-' . time();
            $invoiceData = [
                'type' => 'addon',
                'amount' => (int) $this->record->price,
                'description' => "XpressPOS Add-on: {$addOn->name} ({$this->record->billing_cycle})",
                'customer' => [
                    'given_names' => $user->name,
                    'email' => $user->email,
                    'mobile_number' => $tenant->phone ?? $user->email,
                ],
                'success_redirect_url' => url('/payment/addon/success') . '?addon_id=' . $this->record->id,
                'failure_redirect_url' => url('/payment/failed') . '?addon_id=' . $this->record->id,
                'item_name' => "Add-on: {$addOn->name}",
            ];

            $xenditResponse = $xenditService->createInvoice($invoiceData);

            if (!$xenditResponse['success']) {
                throw new \Exception('Failed to create payment invoice: ' . ($xenditResponse['error'] ?? 'Unknown error'));
            }

            // Create payment record
            $payment = AddOnPayment::create([
                'tenant_add_on_id' => $this->record->id,
                'xendit_invoice_id' => $xenditResponse['data']['id'],
                'external_id' => $externalId,
                'amount' => $this->record->price,
                'status' => 'pending',
                'expires_at' => isset($xenditResponse['data']['expiry_date']) 
                    ? \Carbon\Carbon::parse($xenditResponse['data']['expiry_date']) 
                    : now()->addHours(24),
                'invoice_url' => $xenditResponse['data']['invoice_url'] ?? null,
                'gateway_response' => $xenditResponse['data'],
                'reminder_count' => 0,
            ]);

            DB::commit();

            Log::channel('payment')->info('Add-on payment invoice created', [
                'tenant_id' => $tenant->id,
                'tenant_add_on_id' => $this->record->id,
                'add_on_id' => $addOn->id,
                'add_on_name' => $addOn->name,
                'xendit_invoice_id' => $payment->xendit_invoice_id,
                'external_id' => $payment->external_id,
                'amount' => $payment->amount,
                'billing_cycle' => $this->record->billing_cycle,
            ]);

            // Store payment URL for redirect
            $this->paymentUrl = $xenditResponse['data']['invoice_url'];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->title('Gagal Membuat Payment')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();

            Log::channel('payment')->error('Failed to create add-on payment invoice', [
                'tenant_add_on_id' => $this->record->id,
                'tenant_id' => $tenant->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getFeatureLabel(string $featureCode): string
    {
        return match ($featureCode) {
            'MAX_TRANSACTIONS_PER_MONTH' => 'transaksi/bulan',
            'MAX_STAFF' => 'staff',
            'MAX_STORES' => 'toko',
            'MAX_PRODUCTS' => 'produk',
            default => strtolower(str_replace('MAX_', '', $featureCode)),
        };
    }

    protected function getRedirectUrl(): string
    {
        // If payment URL is set, redirect to Xendit payment page
        if ($this->paymentUrl) {
            return $this->paymentUrl;
        }

        // Otherwise, redirect to view page
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}

