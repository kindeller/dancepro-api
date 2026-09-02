<?php

namespace App\Features\Operations\Actions;

use App\Features\Venues\Models\Venue;
use Illuminate\Http\UploadedFile;

class SaveVenueMap
{
    public function execute(Venue $venue, UploadedFile $map): Venue
    {
        $venue->update(['map_path' => $map->store("operations/venues/{$venue->uuid}", 'public')]);

        return $venue->refresh();
    }
}
