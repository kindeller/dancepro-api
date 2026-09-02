<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Crew\Models\CrewRoleQualification;
use App\Features\Customers\Support\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveCrewProfile
{
    public function execute(array $data, ?CrewProfile $crewProfile = null): CrewProfile
    {
        return DB::transaction(function () use ($data, $crewProfile): CrewProfile {
            $user = $crewProfile?->user ?? new User;
            $user->fill([
                'name' => $data['preferred_name'],
                'email' => $data['email'],
                'type' => UserType::Crew->value,
                'is_active' => $data['is_active'],
                'is_admin' => $data['is_admin'],
            ]);

            if (! $user->exists) {
                $user->password = Str::random(48);
            }

            $user->save();

            $crewProfile ??= new CrewProfile;
            $crewProfile->fill([
                'legal_name' => $data['legal_name'] ?? null,
                'preferred_name' => $data['preferred_name'],
                'phone' => $data['phone'],
                'shirt_size' => $data['shirt_size'] ?? null,
                'jacket_size' => $data['jacket_size'] ?? null,
                'commencement_date' => $data['commencement_date'],
                ...collect($data)->only([
                    'date_of_birth', 'address_line_1', 'address_line_2', 'suburb', 'state', 'postcode',
                    'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
                    'abn', 'bank_account_name', 'bank_name', 'bank_bsb', 'bank_account_number', 'super_fund_name',
                    'super_member_number', 'dietary_requirements', 'medical_information',
                    'drivers_licence_number', 'working_with_children_number', 'working_with_children_expiry',
                    'first_aid_details', 'first_aid_expiry', 'owned_equipment', 'usual_travel_area',
                    'internal_notes',
                ])->all(),
            ]);
            $crewProfile->user()->associate($user);
            $crewProfile->save();

            if (isset($data['profile_photo'])) {
                $crewProfile->profile_photo_path = $data['profile_photo']->store(
                    "crew-profile-photos/{$crewProfile->uuid}",
                    config('operations.filesystem_disk'),
                );
                $crewProfile->save();
            }

            $this->syncQualifications($crewProfile, $data['qualifications'] ?? []);
            $this->syncVehicles($crewProfile, $data['vehicles'] ?? []);

            return $crewProfile->refresh();
        });
    }

    private function syncVehicles(CrewProfile $crewProfile, array $vehicles): void
    {
        $keptIds = [];

        foreach ($vehicles as $vehicleData) {
            $vehicle = filled($vehicleData['uuid'] ?? null)
                ? $crewProfile->vehicles()->where('uuid', $vehicleData['uuid'])->firstOrFail()
                : $crewProfile->vehicles()->make();
            $vehicle->fill(collect($vehicleData)->except('uuid')->all());
            $vehicle->save();
            $keptIds[] = $vehicle->getKey();
        }

        $crewProfile->vehicles()->whereNotIn('id', $keptIds)->delete();
    }

    private function syncQualifications(CrewProfile $crewProfile, array $qualifications): void
    {
        $roleIds = CrewRole::query()->whereIn('id', array_keys($qualifications))->pluck('id');

        $crewProfile->roleQualifications()->whereNotIn('crew_role_id', $roleIds)->delete();

        foreach ($roleIds as $roleId) {
            $qualification = $qualifications[$roleId];

            CrewRoleQualification::query()->updateOrCreate(
                ['crew_profile_id' => $crewProfile->getKey(), 'crew_role_id' => $roleId],
                [
                    'status' => $qualification['status'],
                    'effective_from' => $qualification['effective_from'] ?? null,
                    'effective_until' => $qualification['effective_until'] ?? null,
                    'notes' => $qualification['notes'] ?? null,
                ],
            );
        }
    }
}
