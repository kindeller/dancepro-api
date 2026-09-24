<?php

namespace App\Features\Customers\Middleware;

use App\Features\Customers\Services\CustomerMobileAccount;
use App\Shared\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCustomerMobileAccess
{
    public function __construct(private readonly CustomerMobileAccount $account) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canAccessCustomer() || $request->user()->is_admin) {
            return ApiResponse::error('Customer account access is unavailable.', status: 403);
        }
        $state = $this->account->forUser($request->user());
        if (! $state['email_verified'] || ! $state['policy_accepted']) {
            return ApiResponse::error('Complete account verification and policy onboarding first.', ['onboarding' => ['required']], 403);
        }

        return $next($request);
    }
}
