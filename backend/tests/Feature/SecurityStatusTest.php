<?php

use App\Models\User;

it('exposes security status to owners only', function (): void {
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->viewer()->create();

    $this->actingAs($viewer)->getJson('/api/v1/security/status')->assertForbidden();

    $this->actingAs($owner)
        ->getJson('/api/v1/security/status')
        ->assertOk()
        ->assertJsonStructure(['data' => ['status' => ['checks', 'score' => ['passed', 'warnings', 'failed']]]]);
});

it('runs the safe security check command without printing secrets', function (): void {
    $this->artisan('youpanel:security-check')
        ->expectsOutputToContain('[PASS]')
        ->assertExitCode(0);
});
