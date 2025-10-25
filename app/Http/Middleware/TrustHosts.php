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

        if ($pattern = $this->allSubdomainsOfApplicationUrl()) {
            $hosts[] = $pattern;
        }

        foreach (config('domains', []) as $domain) {
            if (! is_string($domain)) {
                continue;
            }

            $domain = trim($domain);

            if ($domain === '') {
                continue;
            }

            $hosts[] = '^' . preg_quote($domain, '/') . '$';
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = '^' . preg_quote($appHost, '/') . '$';
        }

        $hosts[] = '^127\.0\.0\.1$';
        $hosts[] = '^localhost$';
        $hosts[] = '^\[::1\]$';

        return array_values(array_filter(array_unique($hosts)));
    }
}
