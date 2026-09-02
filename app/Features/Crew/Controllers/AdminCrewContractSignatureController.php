<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Actions\RecordCrewContractSignature;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Requests\RecordCrewContractSignatureRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AdminCrewContractSignatureController extends Controller
{
    public function store(
        RecordCrewContractSignatureRequest $request,
        CrewProfile $crewProfile,
        CrewContract $crewContract,
        RecordCrewContractSignature $recordSignature,
    ): RedirectResponse {
        /** @var User $staff */
        $staff = $request->user();
        $recordSignature->execute(
            $crewProfile,
            $crewContract,
            $request->date('signed_at'),
            $staff,
            $request->string('recording_note')->toString() ?: null,
        );

        return back()->with('status', 'Contract signature recorded. The previous value remains in the audit history.');
    }
}
