<?php

namespace App\Features\Auth\Controllers;

use App\Features\Auth\Requests\ConfirmPasswordRequest;
use App\Features\Auth\Requests\ConfirmTwoFactorSetupRequest;
use App\Features\Auth\Services\TwoFactorAuthentication;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function settings(Request $request, TwoFactorAuthentication $twoFactor): View
    {
        abort_unless(config('security.two_factor.enabled'), 404);

        return view('auth.two-factor-settings', [
            'user' => $request->user(),
            'qrCode' => $twoFactor->qrCodeDataUri($request->user()),
            'recoveryCodes' => session('two_factor_recovery_codes'),
        ]);
    }

    public function begin(ConfirmPasswordRequest $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $twoFactor->begin($request->user());

        return back()->with('status', 'Scan the QR code, then enter the six-digit code to finish setup.');
    }

    public function confirm(ConfirmTwoFactorSetupRequest $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $codes = $twoFactor->confirm($request->user(), $request->string('code')->toString());
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'That authentication code was not valid.']);
        }

        return back()->with('status', 'Two-factor authentication is ready. Save your recovery codes now.')->with('two_factor_recovery_codes', $codes);
    }

    public function recoveryCodes(ConfirmPasswordRequest $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        abort_unless($request->user()->two_factor_confirmed_at !== null, 422);

        return back()->with('status', 'New recovery codes generated. Your old codes no longer work.')->with('two_factor_recovery_codes', $twoFactor->regenerateRecoveryCodes($request->user()));
    }

    public function disable(ConfirmPasswordRequest $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $twoFactor->disable($request->user());

        return back()->with('status', 'Two-factor authentication has been disabled for this account.');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        abort_unless(config('security.two_factor.enabled'), 404);
        if (! $request->session()->has('two_factor_login_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        abort_unless(config('security.two_factor.enabled'), 404);
        $request->validate(['code' => ['nullable', 'digits:6', 'required_without:recovery_code'], 'recovery_code' => ['nullable', 'string', 'required_without:code']]);
        $user = User::query()->findOrFail($request->session()->get('two_factor_login_user_id'));
        $valid = filled($request->input('code'))
            ? $twoFactor->verify($user, $request->string('code')->toString())
            : $twoFactor->useRecoveryCode($user, $request->string('recovery_code')->toString());
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'That authentication or recovery code was not valid.']);
        }

        $remember = (bool) $request->session()->pull('two_factor_login_remember', false);
        $request->session()->forget('two_factor_login_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->save();

        return redirect()->intended(route($user->homeRouteName()));
    }
}
