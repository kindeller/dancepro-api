<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewRoleQualification;
use App\Features\Crew\Support\CrewRoleQualificationStatus;
use Illuminate\Support\Facades\DB;

class UpdateCrewRoleMatrix
{
    public function execute(array $data): void
    {
        $crewProfileIds = collect($data['crew_profile_ids'])->map(fn ($id): int => (int) $id);
        $crewRoleIds = collect($data['crew_role_ids'])->map(fn ($id): int => (int) $id);
        $selected = collect($data['assignments'] ?? []);

        DB::transaction(function () use ($crewProfileIds, $crewRoleIds, $selected): void {
            foreach ($crewProfileIds as $crewProfileId) {
                foreach ($crewRoleIds as $crewRoleId) {
                    $crewAssignments = $selected->get($crewProfileId, []);
                    $isAssigned = (bool) ($crewAssignments[$crewRoleId] ?? false);
                    $qualification = CrewRoleQualification::query()
                        ->where('crew_profile_id', $crewProfileId)
                        ->where('crew_role_id', $crewRoleId)
                        ->first();

                    if ($isAssigned && $qualification === null) {
                        CrewRoleQualification::query()->create([
                            'crew_profile_id' => $crewProfileId,
                            'crew_role_id' => $crewRoleId,
                            'status' => CrewRoleQualificationStatus::Approved,
                        ]);
                    }

                    if (! $isAssigned && $qualification !== null) {
                        $qualification->delete();
                    }
                }
            }
        });
    }
}
