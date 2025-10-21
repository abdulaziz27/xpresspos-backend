<?php

namespace App\Services\Concerns;

use App\Services\StoreContext;
use Illuminate\Support\Facades\Auth;

trait ResolvesStoreContext
{
    protected function resolveStoreId(?array $context = null, bool $allowNull = false): ?string
    {
        $user = Auth::user();
        $storeId = $this->storeContext->current($user);

        if (!$storeId && $context) {
            $storeId = $context['store_id'] ?? null;
        }

        if (!$storeId && !$allowNull) {
            throw new \RuntimeException('Unable to determine store context for this operation.');
        }

        return $storeId;
    }
}
