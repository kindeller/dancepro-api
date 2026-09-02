<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SecureExistingInternalDocuments
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    /** @return array{tracked:int, public:int, already_private:int, missing:int, moved:int, applied:bool} */
    public function execute(bool $apply): array
    {
        $summary = ['tracked' => 0, 'public' => 0, 'already_private' => 0, 'missing' => 0, 'moved' => 0, 'applied' => $apply];

        foreach ($this->paths() as $path) {
            $summary['tracked']++;
            $inPublic = Storage::disk('public')->exists($path);
            $inPrivate = $this->files->disk()->exists($path);

            if ($inPublic) {
                $summary['public']++;
            }

            if ($inPrivate) {
                $summary['already_private']++;
                if ($inPublic && $apply) {
                    $this->ensureSameSize($path);
                    Storage::disk('public')->delete($path);
                }

                continue;
            }

            if (! $inPublic) {
                $summary['missing']++;

                continue;
            }

            if ($apply) {
                $this->move($path);
                $summary['moved']++;
            }
        }

        return $summary;
    }

    /** @return iterable<string> */
    private function paths(): iterable
    {
        $queries = [
            OperationalResource::query()->whereNotNull('file_path')->pluck('file_path'),
            Venue::query()->whereNotNull('map_path')->pluck('map_path'),
            SchedulingEvent::query()->whereNotNull('programme_path')->pluck('programme_path'),
            EventMessage::query()->whereNotNull('attachment_path')->pluck('attachment_path'),
        ];

        foreach (collect($queries)->flatten()->filter()->unique() as $path) {
            yield $path;
        }
    }

    private function move(string $path): void
    {
        $stream = Storage::disk('public')->readStream($path);
        if ($stream === false) {
            throw new RuntimeException("Could not read public internal document: {$path}");
        }

        try {
            if (! $this->files->disk()->writeStream($path, $stream)) {
                throw new RuntimeException("Could not write private internal document: {$path}");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->ensureSameSize($path, true);
        Storage::disk('public')->delete($path);
    }

    private function ensureSameSize(string $path, bool $removePrivateOnFailure = false): void
    {
        if (Storage::disk('public')->size($path) !== $this->files->disk()->size($path)) {
            if ($removePrivateOnFailure) {
                $this->files->disk()->delete($path);
            }

            throw new RuntimeException("Private copy verification failed for: {$path}");
        }
    }
}
