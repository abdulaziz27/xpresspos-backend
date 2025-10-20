<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     */
    public function hosts(): array
    {
        $hosts = [];

        $hosts[] = $this->allSubdomainsOfApplicationUrl();

        $domains = array_filter(config('domains', []));

        foreach ($domains as $domain) {
            $domain = trim((string) $domain);

            if ($domain === '') {
                continue;
            }

            $escaped = preg_quote($domain, '/');
            $hosts[] = '^' . $escaped . '$';

            $parts = explode('.', $domain);
            if (count($parts) >= 2) {
                $root = implode('.', array_slice($parts, -2));
                $escapedRoot = preg_quote($root, '/');
                $hosts[] = '^([A-Za-z0-9-]+\.)?' . $escapedRoot . '$';
            }
        }

        $hosts[] = '^127\.0\.0\.1$';
        $hosts[] = '^localhost$';
        $hosts[] = '^\[::1\]$';

        return array_values(array_filter(array_unique($hosts)));
    }
}
