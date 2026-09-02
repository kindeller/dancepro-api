<?php

namespace App\Features\Venues\Actions;

use App\Features\Venues\Models\Venue;

class CreateVenue
{
    public function execute(array $data): Venue
    {
        return Venue::query()->create($data);
    }
}
