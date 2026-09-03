<?php

namespace App\Features\Crew\Controllers;

use App\Features\Auth\Actions\RotateUserPassword;
use App\Features\Crew\Actions\RefreshCrewOnboardingStatus;
use App\Features\Crew\Actions\UpdateOwnCrewProfile;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Requests\ChangeOwnPasswordRequest;
use App\Features\Crew\Requests\UpdateOwnCrewProfileRequest;
use App\Features\Crew\Services\CrewProfileBadges;
use App\Features\Crew\Support\CrewContractStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrewProfileController extends Controller
{
    public function edit(Request $request, CrewProfileBadges $badges): View
    {
        abort_unless($request->user()?->is_active && $request->user()?->crewProfile !== null, 403);
        $crewProfile = $request->user()->crewProfile->load(['user', 'vehicles', 'contractSignatures.contract']);
        $contracts = CrewContract::query()->where('status', CrewContractStatus::Active)->orderByDesc('effective_from')->get();

        return view('crew.profile.edit', [
            'crewProfile' => $crewProfile,
            'contracts' => $contracts,
            'badgeGroups' => $badges->for($crewProfile),
        ]);
    }

    public function update(UpdateOwnCrewProfileRequest $request, UpdateOwnCrewProfile $updateProfile, RefreshCrewOnboardingStatus $refreshOnboarding): RedirectResponse
    {
        $profile = $updateProfile->execute($request->user()->crewProfile, $request->validated());
        $wasIncomplete = $request->user()->onboarding_completed_at === null;
        $complete = $refreshOnboarding->execute($profile);

        return back()->with('status', $wasIncomplete && $complete
            ? 'Your profile is complete. Welcome to DancePro Crew!'
            : ($wasIncomplete ? 'Profile saved. Please review and sign the required contracts below.' : 'Your profile has been updated.'));
    }

    public function changePassword(ChangeOwnPasswordRequest $request, RotateUserPassword $rotatePassword): RedirectResponse
    {
        $rotatePassword->execute($request->user(), $request->string('password')->toString());

        return back()->with('status', 'Your password has been changed.');
    }
}
