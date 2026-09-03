<?php

namespace App\Features\Auth\Controllers;

use App\Features\Auth\Actions\IssueCrewMobileToken;
use App\Features\Auth\Requests\CrewMobileLoginRequest;
use App\Features\Auth\Resources\CrewMobileUserResource;
use App\Features\Auth\Support\CrewMobileLoginStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileAuthController extends Controller
{
    public function login(CrewMobileLoginRequest $request, IssueCrewMobileToken $issueToken): JsonResponse
    {
        $result = $issueToken->execute($request->validated());

        if ($result->status === CrewMobileLoginStatus::InvalidCredentials) {
            return ApiResponse::error('Invalid credentials.', status: 401);
        }

        if ($result->status === CrewMobileLoginStatus::AccessUnavailable) {
            return ApiResponse::error('Crew app access is unavailable for this account.', status: 403);
        }

        if ($result->status === CrewMobileLoginStatus::TwoFactorSetupRequired) {
            return ApiResponse::error(
                'Two-factor authentication must be configured in the web account before app login.',
                ['two_factor' => ['setup_required']],
                403,
            );
        }

        if ($result->status !== CrewMobileLoginStatus::Passed) {
            $code = $result->status === CrewMobileLoginStatus::TwoFactorRequired ? 'required' : 'invalid';

            return ApiResponse::error(
                $code === 'required' ? 'Two-factor authentication is required.' : 'The authentication or recovery code was not valid.',
                ['two_factor' => [$code]],
                422,
            );
        }

        return ApiResponse::success('Logged in.', [
            'token' => $result->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt?->toIso8601String(),
            'user' => new CrewMobileUserResource($result->user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['last_seen_at' => now()])->save();

        return ApiResponse::success('Authenticated crew member returned.', new CrewMobileUserResource($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out.');
    }
}
