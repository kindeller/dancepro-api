<?php

namespace App\Features\Venues\Actions;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use App\Features\Venues\Support\VenueCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportVenueCatalog
{
    public function __construct(private readonly OperationsFileStorage $operationsFiles) {}

    /** @return array{venues:int,maps:int,references:int,removed:int} */
    public function execute(string $sourceDirectory): array
    {
        $sourceDirectory = rtrim($sourceDirectory, DIRECTORY_SEPARATOR);
        $summary = ['venues' => 0, 'maps' => 0, 'references' => 0, 'removed' => 0];

        DB::transaction(function () use ($sourceDirectory, &$summary): void {
            foreach (VenueCatalog::venues() as $definition) {
                $venue = $this->resolveVenue($definition['name'], $definition['aliases']);
                $venue->fill([
                    'name' => $definition['name'],
                    'address_line_1' => $definition['address'],
                    'address_line_2' => null,
                    'suburb' => $definition['suburb'],
                    'state' => 'WA',
                    'postcode' => $definition['postcode'],
                    'parking_notes' => $definition['parking'],
                    'access_notes' => $definition['access'],
                    'operational_notes' => $definition['operational'],
                ])->save();

                $summary['maps'] += $this->storeImage($venue, $sourceDirectory, $definition['map'], 'map_path');
                $summary['references'] += $this->storeImage($venue, $sourceDirectory, $definition['reference'], 'reference_image_path');
                $summary['venues']++;
            }

            $unmatched = Venue::query()->where('name', 'Fictional Harbour Arts Centre')->first();
            if ($unmatched) {
                $unmatched->delete();
                $summary['removed']++;
            }
        });

        return $summary;
    }

    private function resolveVenue(string $name, array $aliases): Venue
    {
        $canonical = Venue::query()->where('name', $name)->first();
        $alias = $aliases === [] ? null : Venue::query()->whereIn('name', $aliases)->first();

        if ($canonical && $alias && ! $canonical->is($alias)) {
            SchedulingEvent::query()->where('venue_id', $alias->id)->update(['venue_id' => $canonical->id]);
            $alias->delete();
        }

        return $canonical ?? $alias ?? new Venue;
    }

    private function storeImage(Venue $venue, string $sourceDirectory, ?string $filename, string $column): int
    {
        if ($filename === null) {
            return 0;
        }

        $source = $sourceDirectory.DIRECTORY_SEPARATOR.$filename;
        if (! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException("Venue image is missing or unreadable: {$source}");
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $destination = "operations/venues/{$venue->uuid}/".($column === 'map_path' ? 'map' : 'reference').".{$extension}";
        $disk = $column === 'map_path'
            ? $this->operationsFiles->disk()
            : Storage::disk(config('uploads.public_disk'));
        $disk->put($destination, file_get_contents($source));
        $venue->update([$column => $destination]);

        return 1;
    }
}
