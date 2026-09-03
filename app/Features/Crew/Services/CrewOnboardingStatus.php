<?php

namespace App\Features\Crew\Services;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Features\Crew\Support\CrewContractStatus;

class CrewOnboardingStatus
{
    /** @return array{complete: bool, missing: list<string>} */
    public function for(CrewProfile $profile): array
    {
        $requiredProfileFields = [
            'phone',
            'address_line_1',
            'suburb',
            'state',
            'postcode',
            'working_with_children_number',
            'working_with_children_expiry',
        ];

        $missing = collect($requiredProfileFields)
            ->reject(fn (string $field): bool => filled($profile->getAttribute($field)))
            ->map(fn (string $field): string => 'profile.'.$field);

        $activeContracts = CrewContract::query()
            ->where('status', CrewContractStatus::Active)
            ->get(['id', 'uuid']);
        $signedContractIds = $profile->contractSignatures()
            ->where('status', CrewContractSignatureStatus::Signed)
            ->whereIn('crew_contract_id', $activeContracts->pluck('id'))
            ->pluck('crew_contract_id');

        $missingContracts = $activeContracts
            ->whereNotIn('id', $signedContractIds)
            ->map(fn (CrewContract $contract): string => 'contract.'.$contract->uuid);
        $missing = $missing->concat($missingContracts)->values();

        return [
            'complete' => $missing->isEmpty(),
            'missing' => $missing->all(),
        ];
    }
}
