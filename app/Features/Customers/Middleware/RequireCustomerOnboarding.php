<?php

namespace App\Features\Customers\Middleware;

use App\Features\Customers\Support\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCustomerOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->type !== UserType::Customer->value) {
            return $next($request);
        }
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('customer.verification.notice');
        }
        $profile = $user->customerProfile;
        if ($profile && in_array($profile->registration_source, ['web', 'mobile'], true)
            && ($profile->terms_accepted_at === null || $profile->privacy_accepted_at === null
                || $profile->policy_version !== config('customer.policy_version'))) {
            return redirect()->route('customer.onboarding.edit');
        }

        return $next($request);
    }
}
