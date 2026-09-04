<?php

namespace App\Features\Auth\Controllers;

use App\Features\Auth\Requests\ConfirmMobileTwoFactorRequest;
use App\Features\Auth\Requests\ForgotPasswordApiRequest;
use App\Features\Auth\Requests\ManageMobileTwoFactorRequest;
use App\Features\Auth\Services\TwoFactorAuthentication;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CrewMobileSecurityController extends Controller
{
    public function forgotPassword(ForgotPasswordApiRequest $request): JsonResponse
    {
        Password::sendResetLink($request->safe()->only('email'));

        return ApiResponse::success('If an account matches that email, a password reset link has been sent.');
    }

    public function beginTwoFactor(ManageMobileTwoFactorRequest $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $user = $request->user();
        abort_if($user->two_factor_confirmed_at !== null, 422, 'Two-factor authentication is already enabled.');
        if (blank($user->two_factor_secret)) {
            $twoFactor->begin($user);
            $user->refresh();
        }

        return ApiResponse::success('Two-factor setup started.', [
            'secret' => $user->two_factor_secret,
            'otpauth_url' => $twoFactor->provisioningUri($user),
            'recovery_codes' => [],
        ]);
    }

    public function confirmTwoFactor(ConfirmMobileTwoFactorRequest $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        abort_if($request->user()->two_factor_confirmed_at !== null, 422, 'Two-factor authentication is already enabled.');
        $codes = $twoFactor->confirm($request->user(), $request->string('code')->toString());
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'That authentication code was not valid.']);
        }

        return ApiResponse::success('Two-factor authentication enabled.', ['recovery_codes' => $codes]);
    }

    public function recoveryCodes(ManageMobileTwoFactorRequest $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        abort_unless($request->user()->two_factor_confirmed_at !== null, 422, 'Two-factor authentication is not enabled.');

        return ApiResponse::success('Recovery codes regenerated.', [
            'recovery_codes' => $twoFactor->regenerateRecoveryCodes($request->user()),
        ]);
    }

    public function disableTwoFactor(ManageMobileTwoFactorRequest $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        abort_unless($request->user()->two_factor_confirmed_at !== null, 422, 'Two-factor authentication is not enabled.');
        $twoFactor->disable($request->user());
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();
        $request->user()->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        return ApiResponse::success('Two-factor authentication disabled. Other mobile sessions were revoked.');
    }
}
