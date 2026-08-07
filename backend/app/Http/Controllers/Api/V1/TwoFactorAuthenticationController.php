<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Http\Requests\Auth\TwoFactorPasswordRequest;
use App\Services\AuditLogger;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TwoFactorAuthenticationController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'enabled' => $this->twoFactor->enabled($user),
            'confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
            'recovery_codes_remaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->twoFactor->startEnrollment($request->user());

        $this->auditLogger->record('auth.two_factor_started', $request->user(), metadata: ['target_type' => 'user', 'target_identifier' => (string) $request->user()->id], request: $request);

        return ApiResponse::success([
            'enabled' => false,
            'secret' => $payload['secret'],
            'otpauth_url' => $payload['otpauth_url'],
            'qr_code_svg' => $payload['qr_code_svg'],
            'recovery_codes' => $payload['recovery_codes'],
        ], 'Two-factor enrollment started.');
    }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        if (! $this->twoFactor->confirm($request->user(), $request->string('code')->toString())) {
            $this->auditLogger->record('auth.two_factor_confirm_failed', $request->user(), metadata: ['target_type' => 'user', 'target_identifier' => (string) $request->user()->id], request: $request);

            throw ValidationException::withMessages([
                'code' => ['The provided two-factor authentication code is invalid.'],
            ]);
        }

        $this->auditLogger->record('auth.two_factor_enabled', $request->user(), metadata: ['target_type' => 'user', 'target_identifier' => (string) $request->user()->id], request: $request);

        return ApiResponse::success(['enabled' => true], 'Two-factor authentication enabled.');
    }

    public function recoveryCodes(TwoFactorPasswordRequest $request): JsonResponse
    {
        if (! $this->twoFactor->enabled($request->user())) {
            return ApiResponse::error('Two-factor authentication is not enabled.', 422);
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());
        $this->auditLogger->record('auth.two_factor_recovery_codes_regenerated', $request->user(), metadata: ['target_type' => 'user', 'target_identifier' => (string) $request->user()->id], request: $request);

        return ApiResponse::success(['recovery_codes' => $codes], 'Recovery codes regenerated.');
    }

    public function destroy(TwoFactorPasswordRequest $request): JsonResponse
    {
        $this->twoFactor->disable($request->user());
        $this->auditLogger->record('auth.two_factor_disabled', $request->user(), metadata: ['target_type' => 'user', 'target_identifier' => (string) $request->user()->id], request: $request);

        return ApiResponse::success(['enabled' => false], 'Two-factor authentication disabled.');
    }
}
