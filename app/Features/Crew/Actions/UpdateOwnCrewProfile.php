<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewProfile;
use Illuminate\Support\Facades\DB;

class UpdateOwnCrewProfile
{
    public function execute(CrewProfile $crewProfile, array $data): CrewProfile
    {
        return DB::transaction(function () use ($crewProfile, $data): CrewProfile {
            $crewProfile->user->update(['name' => $data['preferred_name'], 'email' => $data['email']]);
            $crewProfile->fill(collect($data)->only([
                'preferred_name', 'legal_name', 'phone', 'shirt_size', 'jacket_size', 'date_of_birth',
                'address_line_1', 'address_line_2', 'suburb', 'state', 'postcode',
                'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
                'abn', 'bank_account_name', 'bank_name', 'bank_bsb', 'bank_account_number',
                'dietary_requirements', 'medical_information',
                'working_with_children_number', 'working_with_children_expiry',
            ])->all())->save();

            if (array_key_exists('vehicles', $data)) {
                $keptVehicleIds = [];
                foreach ($data['vehicles'] as $vehicleData) {
                    $vehicle = filled($vehicleData['uuid'] ?? null)
                        ? $crewProfile->vehicles()->where('uuid', $vehicleData['uuid'])->firstOrFail()
                        : $crewProfile->vehicles()->make();
                    $vehicle->fill(collect($vehicleData)->except('uuid')->all())->save();
                    $keptVehicleIds[] = $vehicle->getKey();
                }
                $crewProfile->vehicles()->whereNotIn('id', $keptVehicleIds)->delete();
            }

            return $crewProfile->refresh();
        });
    }
}
