<?php

namespace App\Features\Customers\Actions;

use App\Features\Auth\Services\ApiLoginTwoFactorGuard;
use App\Features\Auth\Services\ApiLoginTwoFactorResult;
use App\Features\Auth\Support\TokenAbility;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class IssueCustomerMobileToken
{
    public function __construct(private readonly ApiLoginTwoFactorGuard $twoFactor) {}

    public function execute(array $input): array
    {
        $user = User::where('email', strtolower(trim($input['email'])))->first();
        if (! $user || ! Hash::check($input['password'], $user->password)) {
            return ['status' => 'invalid_credentials'];
        }
        if (! $user->canAccessCustomer() || $user->is_admin) {
            return ['status' => 'access_unavailable'];
        }
        $twoFactor = $this->twoFactor->check($user, $input['two_factor_code'] ?? null, $input['recovery_code'] ?? null);
        if ($twoFactor !== ApiLoginTwoFactorResult::Passed) {
            return ['status' => match ($twoFactor) {
                ApiLoginTwoFactorResult::SetupRequired => 'two_factor_setup_required',
                ApiLoginTwoFactorResult::Required => 'two_factor_required',
                default => 'two_factor_invalid',
            }];
        }
        $user->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->save();
        $name = 'customer-mobile:'.$input['device_name'];
        $user->tokens()->where('name', $name)->delete();
        $expiresAt = now()->addMinutes((int) config('security.mobile_token_expiration'));
        $token = $user->createToken($name, [TokenAbility::CustomerMobile->value], $expiresAt);

        return ['status' => 'passed', 'user' => $user, 'token' => $token->plainTextToken, 'expires_at' => $expiresAt];
    }
}
