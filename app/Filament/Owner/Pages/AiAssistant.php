<?php

namespace App\Filament\Owner\Pages;

use App\Models\Store;
use App\Services\Ai\AiAnalyticsService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Carbon\Carbon;
use Livewire\Attributes\On;
use BackedEnum;
use UnitEnum;

class AiAssistant extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Asisten AI';

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.owner.pages.ai-assistant';

    public ?string $question = null;

    public ?string $storeId = null;

    public string $dateRangePreset = 'today'; // today, yesterday, last_7_days, last_30_days, custom

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public array $messages = [];

    public function mount(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => 'Halo! Saya Asisten AI. Silakan tanya apa saja tentang penjualan, stok, atau COGS berdasarkan data di sistem Anda. Pilih rentang tanggal & toko jika perlu, lalu ketik pertanyaan di bawah.',
                'created_at' => now()->toDateTimeString(),
            ],
        ];
    }

    public function sendQuestion(): void
    {
        $this->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $user = auth()->user();

        if (!$user) {
            $this->addErrorMessage('Anda harus login terlebih dahulu.');
            return;
        }

        // Add user message
        $this->messages[] = [
            'role' => 'user',
            'content' => $this->question,
            'created_at' => now()->toDateTimeString(),
        ];

        $userQuestion = $this->question;
        $this->question = null; // Clear input

        // Resolve date range
        [$from, $to] = $this->resolveDateRange();

        try {
            // Call service
            $service = app(AiAnalyticsService::class);

            $answer = $service->analyze(
                $userQuestion,
                $this->storeId,
                $from,
                $to,
                $user,
            );

            // Add assistant response
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $answer,
                'created_at' => now()->toDateTimeString(),
            ];

            // Dispatch browser event to scroll to bottom
            $this->dispatch('message-added');
        } catch (\Exception $e) {
            \Log::error('AI Assistant error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Add error message
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Maaf, terjadi error saat memproses pertanyaan Anda: ' . $e->getMessage(),
                'created_at' => now()->toDateTimeString(),
            ];

            // Dispatch browser event to scroll to bottom
            $this->dispatch('message-added');
        }
    }

    protected function resolveDateRange(): array
    {
        $to = today()->endOfDay();
        $from = today()->startOfDay();

        switch ($this->dateRangePreset) {
            case 'today':
                $from = today()->startOfDay();
                $to = today()->endOfDay();
                break;

            case 'yesterday':
                $from = today()->subDay()->startOfDay();
                $to = today()->subDay()->endOfDay();
                break;

            case 'last_7_days':
                $from = today()->subDays(6)->startOfDay();
                $to = today()->endOfDay();
                break;

            case 'last_30_days':
                $from = today()->subDays(29)->startOfDay();
                $to = today()->endOfDay();
                break;

            case 'custom':
                if ($this->customFrom && $this->customTo) {
                    $from = Carbon::parse($this->customFrom)->startOfDay();
                    $to = Carbon::parse($this->customTo)->endOfDay();
                }
                break;
        }

        return [$from, $to];
    }

    protected function getStoreOptions(): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        $tenantId = $user->currentTenant()?->id;

        if (!$tenantId) {
            return [];
        }

        return Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getDateRangePresetOptions(): array
    {
        return [
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'last_7_days' => '7 Hari Terakhir',
            'last_30_days' => '30 Hari Terakhir',
            'custom' => 'Rentang Kustom',
        ];
    }

    protected function addErrorMessage(string $message): void
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $message,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function clearQuestion(): void
    {
        $this->question = null;
    }
}

