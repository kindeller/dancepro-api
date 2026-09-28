<?php

namespace App\Features\Customers\Controllers;

use App\Features\Auth\Requests\ForgotPasswordApiRequest;
use App\Features\Customers\Actions\AcceptCustomerPolicy;
use App\Features\Customers\Actions\CreateCustomerAccount;
use App\Features\Customers\Actions\IssueCustomerMobileToken;
use App\Features\Customers\Requests\AcceptCustomerPolicyRequest;
use App\Features\Customers\Requests\CustomerMobileLoginRequest;
use App\Features\Customers\Requests\RegisterCustomerRequest;
use App\Features\Customers\Services\CustomerMobileAccount;
use App\Features\Customers\Services\RecordCustomerLogin;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class CustomerMobileAuthController extends Controller
{
    public function forgotPassword(ForgotPasswordApiRequest $request): JsonResponse
    {
        Password::sendResetLink($request->safe()->only('email'));

        return ApiResponse::success('If an account matches that email, a password reset link has been sent.');
    }

    public function register(RegisterCustomerRequest $request, CreateCustomerAccount $create, CustomerMobileAccount $account): JsonResponse
    {
        $user = $create->execute($request->validated(), 'mobile');
        $user->sendEmailVerificationNotification();

        return ApiResponse::success('Account created. Verify your email to continue.', $account->forUser($user), 201);
    }

    public function login(CustomerMobileLoginRequest $request, IssueCustomerMobileToken $issue, CustomerMobileAccount $account, RecordCustomerLogin $loginEvents): JsonResponse
    {
        $result = $issue->execute($request->validated());
        if ($result['status'] !== 'passed') {
            $loginEvents->execute($request, $request->validated('email'), null, false, 'mobile');
            $status = $result['status'];

            return match ($status) {
                'invalid_credentials' => ApiResponse::error('Invalid credentials.', status: 401),
                'access_unavailable', 'two_factor_setup_required' => ApiResponse::error('Customer app access is unavailable for this account.', status: 403),
                default => ApiResponse::error('Two-factor authentication is required or invalid.', ['two_factor' => [$status]], 422),
            };
        }

        $loginEvents->execute($request, $result['user']->email, $result['user'], true, 'mobile');

        return ApiResponse::success('Logged in.', ['token' => $result['token'], 'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(), 'user' => $account->forUser($result['user'])]);
    }

    public function me(Request $request, CustomerMobileAccount $account): JsonResponse
    {
        abort_unless($request->user()?->canAccessCustomer() && ! $request->user()->is_admin, 403);

        return ApiResponse::success('Customer account returned.', $account->forUser($request->user()));
    }

    public function resend(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canAccessCustomer() && ! $request->user()->is_admin, 403);
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return ApiResponse::success('If verification is still needed, a new email has been sent.');
    }

    public function policy(AcceptCustomerPolicyRequest $request, AcceptCustomerPolicy $accept, CustomerMobileAccount $account): JsonResponse
    {
        $accept->execute($request->user());

        return ApiResponse::success('Policies accepted.', $account->forUser($request->user()->fresh()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out.');
    }
}
