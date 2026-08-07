<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TWO_FACTOR_USER_ID = 'auth.two_factor_user_id';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $key = Str::lower($request->string('email')->toString()).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $exception = ValidationException::withMessages([
                'email' => ['Too many login attempts. Please try again soon.'],
            ]);
            $exception->status = 429;

            throw $exception;
        }

        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($key, 60);
            $this->auditLogger->record('auth.login_failed', $user, metadata: ['target_type' => 'user', 'target_identifier' => $request->string('email')->toString()], request: $request);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($key, 60);
            $this->auditLogger->record('auth.login_inactive_rejected', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user->id], request: $request);

            return ApiResponse::error('This account is inactive.', 403);
        }

        RateLimiter::clear($key);
        if ($this->twoFactor->enabled($user)) {
            if (! $request->hasSession()) {
                $this->auditLogger->record('auth.two_factor_session_unavailable', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user->id], request: $request);

                return ApiResponse::error('Session support is required for two-factor authentication.', 500);
            }

            $request->session()->regenerate();
            $request->session()->put(self::TWO_FACTOR_USER_ID, $user->id);

            $this->auditLogger->record('auth.two_factor_required', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user->id], request: $request);

            return ApiResponse::success(['requires_two_factor' => true], 'Two-factor authentication required.', 202);
        }

        $this->completeLogin($request, $user);

        return ApiResponse::success(['user' => new UserResource($user)], 'Logged in.');
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $userId = $request->hasSession() ? $request->session()->get(self::TWO_FACTOR_USER_ID) : null;
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user || ! $user->is_active || ! $this->twoFactor->enabled($user)) {
            $this->auditLogger->record('auth.two_factor_challenge_invalid', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $userId], request: $request);

            return ApiResponse::error('Two-factor challenge has expired. Sign in again.', 401);
        }

        $valid = false;
        if ($request->filled('code')) {
            $valid = $this->twoFactor->validCode($user, $request->string('code')->toString());
        }

        if (! $valid && $request->filled('recovery_code')) {
            $valid = $this->twoFactor->consumeRecoveryCode($user, $request->string('recovery_code')->toString());
        }

        if (! $valid) {
            $this->auditLogger->record('auth.two_factor_failed', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user->id], request: $request);

            throw ValidationException::withMessages([
                'code' => ['The provided two-factor authentication code is invalid.'],
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->forget(self::TWO_FACTOR_USER_ID);
        }

        $this->completeLogin($request, $user);

        return ApiResponse::success(['user' => new UserResource($user)], 'Logged in.');
    }

    private function completeLogin(Request $request, User $user): void
    {
        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->auditLogger->record('auth.login', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user->id], request: $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->auditLogger->record('auth.logout', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user?->id], request: $request);

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success(null, 'Logged out.');
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => new UserResource($request->user())]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return ApiResponse::success(null, 'If that email exists, a password reset link will be sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password)])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('Unable to reset password with the provided token.', 422, ['email' => [__($status)]]);
        }

        return ApiResponse::success(null, 'Password reset.');
    }

    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        $this->auditLogger->record('profile.updated', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user->id], request: $request);

        return ApiResponse::success(['user' => new UserResource($user->refresh())], 'Profile updated.');
    }

    public function updatePassword(PasswordUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->string('password')->toString())])->save();

        $this->auditLogger->record('password.changed', $user, metadata: ['target_type' => 'user', 'target_identifier' => (string) $user->id], request: $request);

        return ApiResponse::success(null, 'Password updated.');
    }
}
