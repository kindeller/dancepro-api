<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewRole;

class CreateCrewRole
{
    public function execute(array $data): CrewRole
    {
        return CrewRole::query()->create($data);
    }
}
