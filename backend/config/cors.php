<?php

$frontendOrigins = array_values(array_filter(array_map(
    fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('FRONTEND_URLS', ''))
)));

if ($frontendOrigins === []) {
    $isProduction = env('APP_ENV') === 'production';

    $frontendOrigins = array_values(array_unique(array_filter([
        rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),
        $isProduction ? null : 'http://localhost:3000',
        $isProduction ? null : 'http://127.0.0.1:3000',
    ])));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $frontendOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 0,
    'supports_credentials' => true,
];
