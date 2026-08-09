<?php

use App\Exceptions\OperationBlockedException;
use App\Services\Security\SafeUrlService;
use Illuminate\Support\Facades\Http;

it('blocks private localhost metadata and ipv4 mapped addresses', function (string $url): void {
    app(SafeUrlService::class)->assertSafeHttpUrl($url);
})->with([
    'localhost' => ['http://localhost/status'],
    'loopback' => ['http://127.0.0.1/status'],
    'metadata' => ['http://169.254.169.254/latest/meta-data'],
    'zero' => ['http://0.0.0.0/status'],
    'ipv6 loopback' => ['http://[::1]/status'],
    'ipv4 mapped loopback' => ['http://[::ffff:127.0.0.1]/status'],
])->throws(OperationBlockedException::class);

it('validates redirect targets before following them', function (): void {
    Http::fake([
        'https://93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
    ]);

    app(SafeUrlService::class)->get('https://93.184.216.34/status');
})->throws(OperationBlockedException::class);
