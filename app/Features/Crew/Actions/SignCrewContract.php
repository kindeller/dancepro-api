<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewContractSignature;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Support\ContractSignatureRecordingMethod;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Features\Crew\Support\CrewContractStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SignCrewContract
{
    public const CONSENT_TEXT = 'I have read and agree to this contract and consent to signing it electronically.';

    public function execute(CrewProfile $crewProfile, CrewContract $contract, string $signedName, ?string $ip, ?string $userAgent): CrewContractSignature
    {
        if ($contract->status !== CrewContractStatus::Active) {
            throw ValidationException::withMessages(['contract' => 'This contract is not currently available for signing.']);
        }

        return DB::transaction(function () use ($crewProfile, $contract, $signedName, $ip, $userAgent): CrewContractSignature {
            $signature = CrewContractSignature::query()->lockForUpdate()->firstOrNew([
                'crew_contract_id' => $contract->getKey(),
                'crew_profile_id' => $crewProfile->getKey(),
            ]);
            if ($signature->status === CrewContractSignatureStatus::Signed) {
                throw ValidationException::withMessages(['contract' => 'This contract has already been signed.']);
            }

            $recordedAt = now();
            $evidence = [
                'status' => CrewContractSignatureStatus::Signed,
                'signed_at' => $recordedAt,
                'signed_name' => $signedName,
                'signer_ip' => $ip,
                'signer_user_agent' => $userAgent,
                'contract_checksum' => $contract->document_checksum ?? hash('sha256', $contract->content ?? ''),
                'consent_text' => self::CONSENT_TEXT,
                'recording_method' => ContractSignatureRecordingMethod::Digital,
                'recorded_by_user_id' => $crewProfile->user_id,
                'recorded_at' => $recordedAt,
                'recording_note' => 'Signed by the crew member through the DancePro Crew Hub.',
            ];
            $signature->fill($evidence)->save();
            $signature->events()->create([
                'previous_status' => null,
                'previous_signed_at' => null,
                'new_status' => CrewContractSignatureStatus::Signed,
                'new_signed_at' => $recordedAt,
                ...collect($evidence)->except(['status', 'signed_at'])->all(),
            ]);

            return $signature->refresh();
        });
    }
}
