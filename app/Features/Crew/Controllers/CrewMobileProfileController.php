<?php

namespace App\Features\Crew\Controllers;

use App\Features\Auth\Actions\RotateUserPassword;
use App\Features\Auth\Resources\CrewMobileUserResource;
use App\Features\Crew\Actions\RefreshCrewOnboardingStatus;
use App\Features\Crew\Actions\UpdateOwnCrewProfile;
use App\Features\Crew\Requests\ChangeOwnPasswordRequest;
use App\Features\Crew\Requests\UpdateCrewMobileProfileRequest;
use App\Features\Crew\Resources\CrewMobileProfileResource;
use App\Features\Crew\Services\CrewOnboardingStatus;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->crewProfile->load(['user', 'vehicles']);

        return ApiResponse::success('Crew profile returned.', new CrewMobileProfileResource($profile));
    }

    public function update(UpdateCrewMobileProfileRequest $request, UpdateOwnCrewProfile $update, RefreshCrewOnboardingStatus $refresh, CrewOnboardingStatus $onboarding): JsonResponse
    {
        $profile = $update->execute($request->user()->crewProfile, $request->profileData());
        $refresh->execute($profile);
        $status = $onboarding->for($profile);

        return ApiResponse::success('Crew profile updated.', [
            'profile' => new CrewMobileProfileResource($profile->load(['user', 'vehicles'])),
            'user' => new CrewMobileUserResource($request->user()->refresh()),
            'onboarding_complete' => $status['complete'],
            'onboarding_missing' => $status['missing'],
        ]);
    }

    public function changePassword(ChangeOwnPasswordRequest $request, RotateUserPassword $rotatePassword): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();
        $rotatePassword->execute(
            $request->user(),
            $request->string('password')->toString(),
            preserveTokenId: is_numeric($currentTokenId) ? (int) $currentTokenId : null,
        );

        return ApiResponse::success('Password changed.', [
            'user' => new CrewMobileUserResource($request->user()->refresh()),
            'session_policy' => $currentTokenId === null
                ? 'all_api_sessions_revoked'
                : 'current_mobile_session_preserved_other_api_sessions_revoked',
        ]);
    }
}
