<?php

namespace App\Features\Tracking\Services;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Services\ConcertTitlePackages;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConcertEditingDownloads
{
    public function __construct(private readonly ConcertTitlePackages $titles, private readonly OperationsFileStorage $files) {}

    public function page(SchedulingEvent $event, User $user): array
    {
        return [
            'programme' => $this->programme($event)['name'] ?? null,
            'titles' => $this->titles->downloadable($event) !== null,
            'videos' => $this->videos($event, $user)->orderBy('uploaded_at')->get(),
        ];
    }

    public function download(SchedulingEvent $event, User $user, string $kind, ?string $file): StreamedResponse
    {
        abort_if($event->cancelled_at !== null, 404);
        $asset = match ($kind) {
            'programme' => $this->programme($event),
            'titles' => $this->titleArchive($event),
            'video' => $this->video($event, $user, $file),
            default => null,
        };
        abort_unless($asset, 404);
        $disk = Storage::disk($asset['disk']);
        abort_unless($disk->exists($asset['path']), 404);

        return $disk->download($asset['path'], $asset['name'], [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function programme(SchedulingEvent $event): ?array
    {
        $programme = Programme::with('source')->where('scheduling_event_id', $event->id)->first();
        if ($source = $programme?->source) {
            return ['disk' => $source->disk, 'path' => $source->path, 'name' => $source->name];
        }
        $file = $event->studioFiles()->where('kind', 'programme')->where('is_current', true)->latest('id')->first();
        if ($file) {
            return ['disk' => $file->disk, 'path' => $file->storage_key, 'name' => $file->original_name];
        }
        $item = $event->concertBookingItem;
        $path = $item?->programme_path ?: $event->programme_path;

        return $path ? ['disk' => $this->files->diskName(), 'path' => $path, 'name' => $item?->programme_name ?: 'Event programme.pdf'] : null;
    }

    private function titleArchive(SchedulingEvent $event): ?array
    {
        $package = $this->titles->downloadable($event);

        return $package ? ['disk' => $package->disk, 'path' => $package->path, 'name' => 'Video titles '.$package->uuid.'.zip'] : null;
    }

    private function videos(SchedulingEvent $event, User $user): Builder
    {
        return $event->studioFiles()->getQuery()->where('kind', 'studio_video')->where('is_current', true)
            ->when(! $user->canAccessAdmin(), fn (Builder $query) => $query->where('status', 'approved'));
    }

    private function video(SchedulingEvent $event, User $user, ?string $uuid): ?array
    {
        $file = $this->videos($event, $user)->where('uuid', $uuid)->first();

        return $file ? ['disk' => $file->disk, 'path' => $file->storage_key, 'name' => $file->original_name] : null;
    }
}
