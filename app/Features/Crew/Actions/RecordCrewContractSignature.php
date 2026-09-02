<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewContractSignature;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Support\ContractSignatureRecordingMethod;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordCrewContractSignature
{
    public function execute(
        CrewProfile $crewProfile,
        CrewContract $contract,
        CarbonInterface $signedAt,
        User $recordedBy,
        ?string $note = null,
    ): CrewContractSignature {
        return DB::transaction(function () use ($crewProfile, $contract, $signedAt, $recordedBy, $note): CrewContractSignature {
            $signature = CrewContractSignature::query()
                ->lockForUpdate()
                ->firstOrNew([
                    'crew_contract_id' => $contract->getKey(),
                    'crew_profile_id' => $crewProfile->getKey(),
                ]);

            $previousStatus = $signature->exists ? $signature->status : null;
            $previousSignedAt = $signature->signed_at;
            $recordingMethod = $previousSignedAt === null
                ? ContractSignatureRecordingMethod::ManualExisting
                : ContractSignatureRecordingMethod::ManualCorrection;
            $recordedAt = now();

            $signature->fill([
                'status' => CrewContractSignatureStatus::Signed,
                'signed_at' => $signedAt,
                'recording_method' => $recordingMethod,
                'recorded_by_user_id' => $recordedBy->getKey(),
                'recorded_at' => $recordedAt,
                'recording_note' => $note,
            ])->save();

            $signature->events()->create([
                'previous_status' => $previousStatus,
                'previous_signed_at' => $previousSignedAt,
                'new_status' => CrewContractSignatureStatus::Signed,
                'new_signed_at' => $signedAt,
                'recording_method' => $recordingMethod,
                'recorded_by_user_id' => $recordedBy->getKey(),
                'recorded_at' => $recordedAt,
                'recording_note' => $note,
            ]);

            return $signature->refresh();
        });
    }
}
