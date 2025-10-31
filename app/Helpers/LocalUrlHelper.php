<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class LocalUrlHelper
{
    /**
     * Generate environment-aware route URL
     */
    public static function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        // If in local environment and request is from local host, use request domain
        if (self::shouldUseLocalUrls()) {
            return self::generateLocalUrl($name, $parameters, $absolute);
        }
        
        // Use default Laravel route helper for production
        return route($name, $parameters, $absolute);
    }
    
    /**
     * Check if we should use local URLs
     */
    private static function shouldUseLocalUrls(): bool
    {
        if (!app()->environment('local')) {
            return false;
        }
        
        $request = request();
        if (!$request) {
            return false;
        }
        
        return self::isLocalRequest($request);
    }
    
    /**
     * Check if request is from local development
     */
    private static function isLocalRequest(Request $request): bool
    {
        $host = $request->getHost();
        $localHosts = ['127.0.0.1', 'localhost'];
        
        return in_array($host, $localHosts) || 
               str_contains($host, '.test') ||
               str_contains($host, '.local');
    }
    
    /**
     * Generate local URL using request domain
     */
    private static function generateLocalUrl(string $name, array $parameters, bool $absolute): string
    {
        $request = request();
        
        // Generate relative URL first
        $relativeUrl = route($name, $parameters, false);
        
        if (!$absolute) {
            return $relativeUrl;
        }
        
        // Build absolute URL using current request domain
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();
        
        $baseUrl = $scheme . '://' . $host;
        if ($port && !in_array($port, [80, 443])) {
            $baseUrl .= ':' . $port;
        }
        
        return $baseUrl . $relativeUrl;
    }
}