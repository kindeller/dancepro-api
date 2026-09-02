<?php

namespace App\Features\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If an account matches that email, a password reset link has been sent.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
            'onboarding' => $request->boolean('onboarding'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'onboarding' => ['sometimes', 'boolean'],
        ]);
        $resetUser = null;
        $credentials = collect($validated)->only(['email', 'password', 'password_confirmation', 'token'])->all();
        $status = Password::reset($credentials, function (User $user, string $password) use (&$resetUser): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
            $resetUser = $user;
            event(new PasswordReset($user));
        });

        if ($status === Password::PasswordReset && $request->boolean('onboarding') && $resetUser?->crewProfile !== null) {
            Auth::login($resetUser);
            $request->session()->regenerate();

            return redirect()->route('crew.profile.edit')->with('status', 'Password created. Please complete your crew profile.');
        }

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', 'Your password has been reset. You can sign in now.')
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
