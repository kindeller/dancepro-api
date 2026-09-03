<?php

namespace App\Features\Auth\Actions;

use App\Features\Auth\Services\ApiLoginTwoFactorGuard;
use App\Features\Auth\Services\ApiLoginTwoFactorResult;
use App\Features\Auth\Support\CrewMobileLoginResult;
use App\Features\Auth\Support\CrewMobileLoginStatus;
use App\Features\Auth\Support\TokenAbility;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class IssueCrewMobileToken
{
    public function __construct(private readonly ApiLoginTwoFactorGuard $twoFactor) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data): CrewMobileLoginResult
    {
        /** @var User|null $user */
        $user = User::query()->with('crewProfile')->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return new CrewMobileLoginResult(CrewMobileLoginStatus::InvalidCredentials);
        }

        if (! $user->canAccessCrew()) {
            return new CrewMobileLoginResult(CrewMobileLoginStatus::AccessUnavailable);
        }

        $twoFactorResult = $this->twoFactor->check(
            $user,
            $data['two_factor_code'] ?? null,
            $data['recovery_code'] ?? null,
        );
        $failedStatus = match ($twoFactorResult) {
            ApiLoginTwoFactorResult::SetupRequired => CrewMobileLoginStatus::TwoFactorSetupRequired,
            ApiLoginTwoFactorResult::Required => CrewMobileLoginStatus::TwoFactorRequired,
            ApiLoginTwoFactorResult::Invalid => CrewMobileLoginStatus::TwoFactorInvalid,
            ApiLoginTwoFactorResult::Passed => null,
        };

        if ($failedStatus !== null) {
            return new CrewMobileLoginResult($failedStatus);
        }

        $user->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->save();
        $tokenName = 'crew-mobile:'.$data['device_name'];
        $user->tokens()->where('name', $tokenName)->delete();
        $expiresAt = now()->addMinutes((int) config('security.mobile_token_expiration'));
        $token = $user->createToken($tokenName, [TokenAbility::CrewMobile->value], $expiresAt);

        return new CrewMobileLoginResult(
            CrewMobileLoginStatus::Passed,
            $user,
            $token->plainTextToken,
            $expiresAt,
        );
    }
}
