<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Actions\RefreshCrewOnboardingStatus;
use App\Features\Crew\Actions\SignCrewContract;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Requests\SignCrewContractRequest;
use App\Features\Crew\Support\CrewContractStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrewContractController extends Controller
{
    public function show(Request $request, CrewContract $crewContract): View
    {
        abort_unless($crewContract->status === CrewContractStatus::Active, 404);
        $signature = $request->user()->crewProfile->contractSignatures()
            ->where('crew_contract_id', $crewContract->id)->first();

        return view('crew.contracts.show', compact('crewContract', 'signature'));
    }

    public function sign(SignCrewContractRequest $request, CrewContract $crewContract, SignCrewContract $signContract, RefreshCrewOnboardingStatus $refreshOnboarding): RedirectResponse
    {
        $profile = $request->user()->crewProfile;
        $signContract->execute($profile, $crewContract, $request->string('signed_name')->toString(), $request->ip(), $request->userAgent());
        $complete = $refreshOnboarding->execute($profile->refresh());

        return redirect()->route('crew.profile.edit')->with('status', $complete
            ? 'Contract signed. Your onboarding is complete.'
            : 'Contract signed successfully.');
    }
}
