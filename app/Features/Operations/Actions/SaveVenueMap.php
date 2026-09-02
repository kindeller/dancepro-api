<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Venues\Models\Venue;
use Illuminate\Http\UploadedFile;

class SaveVenueMap
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    public function execute(Venue $venue, UploadedFile $map): Venue
    {
        $previousPath = $venue->map_path;
        $venue->update(['map_path' => $this->files->store($map, "operations/venues/{$venue->uuid}")]);
        $this->files->deleteReplaced($previousPath, $venue->map_path);

        return $venue->refresh();
    }
}
