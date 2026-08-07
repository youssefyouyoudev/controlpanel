<?php

namespace App\Support;

use Illuminate\Http\Middleware\TrustProxies;
use ReflectionClass;

class TrustedProxyConfiguration
{
    public static function configured(): bool
    {
        $proxies = self::alwaysTrustProxies();

        if (is_string($proxies)) {
            return trim($proxies) !== '';
        }

        if (is_array($proxies)) {
            foreach ($proxies as $proxy) {
                if (is_string($proxy) && trim($proxy) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>|string|null
     */
    private static function alwaysTrustProxies(): array|string|null
    {
        $property = (new ReflectionClass(TrustProxies::class))->getProperty('alwaysTrustProxies');
        $property->setAccessible(true);

        $value = $property->getValue();

        return is_array($value) || is_string($value) || $value === null ? $value : null;
    }
}
