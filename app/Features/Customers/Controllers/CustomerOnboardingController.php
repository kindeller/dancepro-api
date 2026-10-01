<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\AcceptCustomerPolicy;
use App\Features\Customers\Actions\VerifyCustomerEmail;
use App\Features\Customers\Requests\AcceptCustomerPolicyRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerOnboardingController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->canAccessCustomer(), 403);

        return $request->user()->hasVerifiedEmail() ? ($request->session()->has('family_invitation_url') ? redirect($request->session()->get('family_invitation_url')) : to_route($request->session()->has('publicity_join') ? 'publicity.join.complete' : 'customer.media.index'))
            : view('customers.account.verify-email');
    }

    public function resend(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canAccessCustomer(), 403);
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'If verification is still needed, a new email has been sent.');
    }

    public function verify(Request $request, int $id, string $hash, VerifyCustomerEmail $verify): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless($user->canAccessCustomer() && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        $verify->execute($user);

        if ($request->user()?->is($user)) {
            Auth::setUser($user);
        }

        return $request->user()?->is($user)
            ? ($request->session()->has('family_invitation_url') ? redirect($request->session()->get('family_invitation_url')) : to_route($request->session()->has('publicity_join') ? 'publicity.join.complete' : 'customer.media.index'))->with('status', 'Email verified.')
            : to_route('login')->with('status', 'Email verified. Sign in to continue.');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->canAccessCustomer(), 403);
        if (! $request->user()->hasVerifiedEmail()) {
            return to_route('customer.verification.notice');
        }

        return view('customers.account.onboarding');
    }

    public function store(AcceptCustomerPolicyRequest $request, AcceptCustomerPolicy $accept): RedirectResponse
    {
        $accept->execute($request->user());

        return $request->session()->has('family_invitation_url') ? redirect($request->session()->get('family_invitation_url')) : to_route($request->session()->has('publicity_join') ? 'publicity.join.complete' : 'customer.media.index');
    }
}
