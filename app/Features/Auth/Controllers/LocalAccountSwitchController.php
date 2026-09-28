<?php

namespace App\Features\Auth\Controllers;

use App\Features\Auth\Requests\SwitchLocalAccountRequest;
use App\Features\Auth\Services\LocalAccountSwitcher;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LocalAccountSwitchController extends Controller
{
    public function __invoke(SwitchLocalAccountRequest $request, LocalAccountSwitcher $switcher): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $account = $switcher->findAllowed($request->integer('user_id'));
        abort_if($account === null, 404);

        $user = User::query()->findOrFail($account['id']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return redirect()->route($account['destination'])
            ->with('status', "Testing as {$user->name}.");
    }
}
