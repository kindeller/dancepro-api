<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewRole;

class UpdateCrewRole
{
    public function execute(CrewRole $crewRole, array $data): CrewRole
    {
        $crewRole->update($data);

        return $crewRole->refresh();
    }
}
