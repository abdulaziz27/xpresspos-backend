<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class StoreContext
{
    private const SESSION_KEY = 'store_context.current_store_id';

    private ?string $cache = null;

    public function __construct(private readonly SessionContract|SessionManager|null $session = null)
    {
    }

    public static function instance(): self
    {
        return App::make(self::class);
    }

    public function current(?User $user = null): ?string
    {
        if ($this->cache) {
            return $this->cache;
        }

        $storeId = $this->session()?->get(self::SESSION_KEY);

        if (!$storeId && $user) {
            $storeId = $this->fallbackStoreId($user);
        }

        if ($user && $storeId && !$this->userHasAccessToStore($user, $storeId)) {
            $storeId = $this->fallbackStoreId($user);
        }

        $this->cache = $storeId ?: null;

        return $this->cache;
    }

    public function set(?string $storeId): void
    {
        $this->cache = $storeId;

        if ($storeId) {
            $this->session()?->put(self::SESSION_KEY, $storeId);
        } else {
            $this->session()?->forget(self::SESSION_KEY);
        }
    }

    public function setForUser(User $user, string $storeId): bool
    {
        if (!$this->userHasAccessToStore($user, $storeId)) {
            return false;
        }

        $this->set($storeId);

        return true;
    }

    public function clear(): void
    {
        $this->cache = null;
        $this->session()?->forget(self::SESSION_KEY);
    }

    public function accessibleStores(User $user): Collection
    {
        return $user->stores()->get();
    }

    private function userHasAccessToStore(User $user, string $storeId): bool
    {
        return $user->stores()
            ->where('stores.id', $storeId)
            ->exists();
    }

    private function fallbackStoreId(User $user): ?string
    {
        $primary = $user->storeAssignments()
            ->where('is_primary', true)
            ->first();

        if ($primary?->store_id) {
            return $primary->store_id;
        }

        return $user->storeAssignments()->value('store_id');
    }

    private function session(): ?SessionContract
    {
        if ($this->session instanceof SessionContract) {
            return $this->session;
        }

        if (!App::getFacadeApplication()->bound('session')) {
            return null;
        }

        $session = App::getFacadeApplication()->make('session');

        if ($session instanceof SessionContract) {
            return $session;
        }

        if (method_exists($session, 'driver')) {
            $driver = $session->driver();

            return $driver instanceof SessionContract ? $driver : null;
        }

        return null;
    }
}
