<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Operations\SecretRedactor;

it('recursively redacts nested audit metadata secrets', function (): void {
    $user = User::factory()->owner()->create();

    app(AuditLogger::class)->record('security.redaction.test', $user, null, [
        'target_type' => 'test',
        'target_identifier' => 'redaction',
        'deployment' => [
            'credentials' => [
                'token' => 'sensitive-token',
                'headers' => [
                    'Authorization' => 'Bearer sensitive',
                    'Set-Cookie' => 'session=sensitive',
                ],
            ],
            'url' => 'https://user:password@example.com/repo.git',
        ],
        'object' => (object) ['client_secret' => 'secret-value'],
    ]);

    $metadata = AuditLog::query()->where('action', 'security.redaction.test')->firstOrFail()->metadata;
    $encoded = json_encode($metadata, JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('sensitive-token')
        ->and($encoded)->not->toContain('Bearer sensitive')
        ->and($encoded)->not->toContain('session=sensitive')
        ->and($encoded)->not->toContain('user:password')
        ->and($encoded)->not->toContain('secret-value')
        ->and($encoded)->toContain('[redacted]');
});

it('redacts credential-bearing strings', function (): void {
    $redacted = app(SecretRedactor::class)->redact('Authorization: Basic abc123 password=hunter2 token=sensitive https://u:p@example.com');

    expect($redacted)->not->toContain('abc123')
        ->and($redacted)->not->toContain('hunter2')
        ->and($redacted)->not->toContain('sensitive')
        ->and($redacted)->not->toContain('u:p');
});
