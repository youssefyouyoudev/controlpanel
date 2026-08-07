<?php

namespace App\Support;

class SanctumStatefulDomains
{
    /**
     * @return array<int, string>
     */
    public static function fromEnvironment(): array
    {
        $defaults = env('APP_ENV') === 'production'
            ? []
            : ['localhost', 'localhost:3000', '127.0.0.1', '127.0.0.1:3000', '127.0.0.1:8000', '::1'];

        $values = [
            ...$defaults,
            ...explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', '')),
            ...explode(',', (string) env('FRONTEND_URLS', '')),
            env('FRONTEND_URL'),
        ];

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $value): ?string => self::normalize(is_scalar($value) ? (string) $value : null),
            $values
        ))));
    }

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);
            $port = parse_url($value, PHP_URL_PORT);

            return $host ? $host.($port ? ':'.$port : '') : null;
        }

        return rtrim(preg_replace('#/.*$#', '', $value) ?: '', '/') ?: null;
    }
}
