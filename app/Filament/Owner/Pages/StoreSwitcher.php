<?php

namespace App\Filament\Owner\Pages;

use App\Models\Store;
use App\Services\StoreContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use UnitEnum;

class StoreSwitcher extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Ganti Toko';

    protected static ?string $slug = 'switch-store';

    protected static string|UnitEnum|null $navigationGroup = 'Store Operations';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.owner.pages.store-switcher';

    public array $stores = [];

    public ?string $activeStoreId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        $storeContext = StoreContext::instance();
        $this->activeStoreId = $storeContext->current($user);

        $this->stores = $storeContext->accessibleStores($user)
            ->map(fn(Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'status' => $store->status,
                'email' => $store->email,
                'is_active' => $store->id === $this->activeStoreId,
            ])
            ->toArray();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pilih Toko Aktif';
    }

    public function selectStore(string $storeId): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        $storeContext = StoreContext::instance();

        if (!$storeContext->setForUser($user, $storeId)) {
            Notification::make()
                ->title('Anda tidak memiliki akses ke toko ini.')
                ->danger()
                ->send();

            return;
        }

        $this->activeStoreId = $storeId;
        $this->redirect(request()->header('Referer') ?? static::getUrl());
    }
}
