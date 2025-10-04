<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        $hosts = [
            $this->allSubdomainsOfApplicationUrl(),
        ];

        $hosts[] = '^127\.0\.0\.1$';
        $hosts[] = '^localhost$';
        $hosts[] = '^\[::1\]$';

        return array_filter($hosts);
    }
}
