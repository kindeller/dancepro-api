<?php

namespace App\Features\Auth\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthentication
{
    public function __construct(private readonly Google2FA $totp = new Google2FA) {}

    public function begin(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->totp->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function confirm(User $user, string $code): ?array
    {
        if (blank($user->two_factor_secret) || ! $this->totp->verifyKey($user->two_factor_secret, $code, 1)) {
            return null;
        }

        return $this->regenerateRecoveryCodes($user, confirm: true);
    }

    public function verify(User $user, string $code): bool
    {
        return filled($user->two_factor_secret) && $this->totp->verifyKey($user->two_factor_secret, $code, 1);
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        foreach ($user->two_factor_recovery_codes ?? [] as $index => $hash) {
            if (! Hash::check(Str::lower(trim($code)), $hash)) {
                continue;
            }

            $codes = $user->two_factor_recovery_codes;
            unset($codes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

            return true;
        }

        return false;
    }

    public function regenerateRecoveryCodes(User $user, bool $confirm = false): array
    {
        $plainCodes = collect(range(1, 8))->map(fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $attributes = ['two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $plainCodes)];
        if ($confirm) {
            $attributes['two_factor_confirmed_at'] = now();
        }
        $user->forceFill($attributes)->save();

        return $plainCodes;
    }

    public function disable(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
    }

    public function qrCodeDataUri(User $user): ?string
    {
        if (blank($user->two_factor_secret)) {
            return null;
        }

        $uri = $this->totp->getQRCodeUrl(config('security.two_factor.issuer'), $user->email, $user->two_factor_secret);
        $renderer = new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($uri);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
