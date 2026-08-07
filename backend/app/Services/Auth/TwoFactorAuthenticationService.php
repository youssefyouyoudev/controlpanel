<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * @return array{secret: string, otpauth_url: string, qr_code_svg: string, recovery_codes: list<string>}
     */
    public function startEnrollment(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey(32);
        $recoveryCodes = $this->plainRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => null,
        ])->save();

        $otpauthUrl = $this->otpauthUrl($user, $secret);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_code_svg' => $this->qrCodeSvg($otpauthUrl),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function confirm(User $user, string $code): bool
    {
        if (! $user->two_factor_secret || ! $this->validCode($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function enabled(User $user): bool
    {
        return filled($user->two_factor_secret) && filled($user->two_factor_confirmed_at);
    }

    public function validCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return $this->google2fa->verifyKey($user->two_factor_secret, preg_replace('/\s+/', '', $code), 1);
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = Str::upper(trim($code));
        $stored = collect($user->two_factor_recovery_codes ?? []);
        $matched = null;

        foreach ($stored as $index => $hash) {
            if (Hash::check($normalized, (string) $hash)) {
                $matched = $index;
                break;
            }
        }

        if ($matched === null) {
            return false;
        }

        $remaining = $stored->reject(fn (string $hash, int $index): bool => $index === $matched)->values()->all();
        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->plainRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->hashRecoveryCodes($codes)])->save();

        return $codes;
    }

    private function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name', 'YouPanel'), $user->email, $secret);
    }

    private function qrCodeSvg(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($otpauthUrl);
    }

    /**
     * @return list<string>
     */
    private function plainRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function hashRecoveryCodes(array $codes): array
    {
        return collect($codes)
            ->map(fn (string $code): string => Hash::make(Str::upper(trim($code))))
            ->values()
            ->all();
    }
}
