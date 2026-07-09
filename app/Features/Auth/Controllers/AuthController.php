<?php

namespace App\Features\Auth\Controllers;

use App\Features\Auth\Requests\LoginRequest;
use App\Features\Auth\Resources\AuthUserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Issue a Sanctum API token for an active staff/admin user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return ApiResponse::error('Invalid credentials.', status: 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('This account is inactive.', status: 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $token = $user->createToken(
            name: $request->string('device_name', 'dancepro-api')->toString(),
            abilities: ['*'],
        );

        return ApiResponse::success('Logged in.', [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new AuthUserResource($user),
        ]);
    }

    /**
     * Return the authenticated API user.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'last_seen_at' => now(),
        ])->save();

        return ApiResponse::success('Authenticated user returned.', [
            'user' => new AuthUserResource($user),
        ]);
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out.');
    }
}
