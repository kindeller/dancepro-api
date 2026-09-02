<?php

namespace App\Features\Deployment\Actions;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Venues\Models\Venue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MigratePublicUploads
{
    /** @return array{tracked:int,copied:int,already_present:int,missing:int,applied:bool} */
    public function execute(string $sourceDisk = 'public', bool $apply = false): array
    {
        $targetDisk = (string) config('uploads.public_disk');
        if ($sourceDisk === $targetDisk) {
            throw new RuntimeException('The public upload source and target disks must be different.');
        }

        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);
        $summary = ['tracked' => 0, 'copied' => 0, 'already_present' => 0, 'missing' => 0, 'applied' => $apply];

        foreach ($this->paths() as $path) {
            $summary['tracked']++;
            if ($target->exists($path)) {
                $summary['already_present']++;

                continue;
            }
            if (! $source->exists($path)) {
                $summary['missing']++;

                continue;
            }
            if (! $apply) {
                $summary['copied']++;

                continue;
            }

            $stream = $source->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException("Unable to read public upload [{$path}].");
            }

            try {
                if (! $target->writeStream($path, $stream)) {
                    throw new RuntimeException("Unable to copy public upload [{$path}].");
                }
            } finally {
                fclose($stream);
            }
            $summary['copied']++;
        }

        return $summary;
    }

    /** @return list<string> */
    private function paths(): array
    {
        return collect()
            ->merge(Studio::query()->whereNotNull('logo_path')->pluck('logo_path'))
            ->merge(CompetitionContact::query()->whereNotNull('logo_path')->pluck('logo_path'))
            ->merge(SchedulingEvent::query()->whereNotNull('logo_path')->pluck('logo_path'))
            ->merge(Venue::query()->whereNotNull('reference_image_path')->pluck('reference_image_path'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
