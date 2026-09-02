<?php

namespace Database\Factories;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewContractSignature;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewContractSignature> */
class CrewContractSignatureFactory extends Factory
{
    protected $model = CrewContractSignature::class;

    public function definition(): array
    {
        return [
            'crew_contract_id' => CrewContract::factory(),
            'crew_profile_id' => CrewProfile::factory(),
            'status' => CrewContractSignatureStatus::Pending,
        ];
    }
}
