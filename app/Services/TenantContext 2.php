<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class TenantContext
{
    private const SESSION_KEY = 'tenant_context.current_tenant_id';

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

        $tenantId = $this->session()?->get(self::SESSION_KEY);

        if (!$tenantId && $user) {
            $tenantId = $this->fallbackTenantId($user);
        }

        if ($user && $tenantId && !$this->userHasAccessToTenant($user, $tenantId)) {
            $tenantId = $this->fallbackTenantId($user);
        }

        $this->cache = $tenantId ?: null;
        return $this->cache;
    }

    public function set(?string $tenantId): void
    {
        $this->cache = $tenantId;

        if ($tenantId) {
            $this->session()?->put(self::SESSION_KEY, $tenantId);
        } else {
            $this->session()?->forget(self::SESSION_KEY);
        }
    }

    public function setForUser(User $user, string $tenantId): bool
    {
        if (!$this->userHasAccessToTenant($user, $tenantId)) {
            return false;
        }

        $this->set($tenantId);
        return true;
    }

    public function clear(): void
    {
        $this->cache = null;
        $this->session()?->forget(self::SESSION_KEY);
    }

    public function accessibleTenants(User $user): Collection
    {
        return $user->tenants()->get();
    }

    private function userHasAccessToTenant(User $user, string $tenantId): bool
    {
        return $user->tenants()
            ->where('tenants.id', $tenantId)
            ->exists();
    }

    private function fallbackTenantId(User $user): ?string
    {
        // Get from primary store's tenant
        $primaryStore = $user->primaryStore();
        if ($primaryStore?->tenant_id) {
            return $primaryStore->tenant_id;
        }

        // Get first tenant access
        $firstTenant = $user->tenants()->first();
        return $firstTenant?->id;
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

