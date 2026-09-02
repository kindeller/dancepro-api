<?php

namespace App\Features\Auth\Controllers;

use App\Features\Auth\Requests\WebLoginRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(WebLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'The provided credentials could not be verified.'])
                ->onlyInput('email');
        }

        /** @var User $user */
        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'This account is inactive.'])
                ->onlyInput('email');
        }

        if (config('security.two_factor.enabled') && $user->two_factor_confirmed_at !== null) {
            $request->session()->put('two_factor_login_user_id', $user->id);
            $request->session()->put('two_factor_login_remember', $request->boolean('remember'));
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        if (config('security.two_factor.enabled') && config('security.two_factor.enforced') && $user->two_factor_confirmed_at === null) {
            return redirect()->route('account.security');
        }

        return redirect()->intended(route($user->homeRouteName()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
