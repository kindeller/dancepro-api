<?php

namespace App\Features\Auth\Services;

use App\Models\User;

class ApiLoginTwoFactorGuard
{
    public function __construct(private readonly TwoFactorAuthentication $twoFactor) {}

    public function check(User $user, ?string $code, ?string $recoveryCode): ApiLoginTwoFactorResult
    {
        if (! config('security.two_factor.enabled')) {
            return ApiLoginTwoFactorResult::Passed;
        }

        if ($user->two_factor_confirmed_at === null) {
            return config('security.two_factor.enforced')
                ? ApiLoginTwoFactorResult::SetupRequired
                : ApiLoginTwoFactorResult::Passed;
        }

        if (filled($code) && $this->twoFactor->verify($user, $code)) {
            return ApiLoginTwoFactorResult::Passed;
        }

        if (filled($recoveryCode) && $this->twoFactor->useRecoveryCode($user, $recoveryCode)) {
            return ApiLoginTwoFactorResult::Passed;
        }

        return filled($code) || filled($recoveryCode)
            ? ApiLoginTwoFactorResult::Invalid
            : ApiLoginTwoFactorResult::Required;
    }
}
