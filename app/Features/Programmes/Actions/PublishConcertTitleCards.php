<?php

namespace App\Features\Programmes\Actions;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Programmes\Models\ConcertTitleCardPackage;
use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Services\ConcertTitlePackages;
use App\Features\Programmes\Services\ValidateTitleCardArchive;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PublishConcertTitleCards
{
    public function __construct(private readonly OperationsFileStorage $files, private readonly ConcertTitlePackages $packages, private readonly ValidateTitleCardArchive $archives, private readonly SaveConcertTrackerTaskFields $tracker) {}

    public function execute(SchedulingEvent $event, User $actor, array $input, UploadedFile $upload): ConcertTitleCardPackage
    {
        abort_unless($event->event_type->value === 'concert' && ! $event->cancelled_at, 404);

        return DB::transaction(function () use ($event, $actor, $input, $upload): ConcertTitleCardPackage {
            $programme = Programme::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $revision = $programme->latestRevision;
            abort_unless($revision && $revision->uuid === $input['revision_uuid'] && hash_equals($this->packages->fingerprint($event, $revision), $input['content_fingerprint']), 409, 'The programme or event changed. Reload the page and prepare the titles again.');
            $items = collect($revision->snapshot['nodes'])->where('kind', 'item')->filter(fn ($item) => ($item['merged_into_uuid'] ?? null) === null);
            $this->archives->validate($upload, $items->count());
            $settings = Arr::only($input, ConcertTitlePackages::SETTINGS);
            $path = $this->files->store($upload, 'concert-title-cards/'.$event->uuid);
            abort_unless($path, 503, 'The title package could not be saved. Please try again.');
            ConcertTitleCardTemplate::updateOrCreate(['scheduling_event_id' => $event->id], $settings + ['font_family' => 'Atlanta', 'updated_by_user_id' => $actor->id]);
            $package = ConcertTitleCardPackage::create([
                'scheduling_event_id' => $event->id, 'programme_revision_id' => $revision->id, 'published_by_user_id' => $actor->id,
                'disk' => $this->files->diskName(), 'path' => $path, 'sha256' => hash_file('sha256', $upload->getRealPath()),
                'size_bytes' => $upload->getSize(), 'item_count' => $items->count(), 'settings' => $settings, 'content_fingerprint' => $input['content_fingerprint'],
            ]);
            // Reset completion metadata so each publication records its publisher and date.
            $this->tracker->execute('event', $event->id, 'video_titles', $actor, ['status' => 'in_progress', 'status_override' => 'in_progress']);
            $this->tracker->execute('event', $event->id, 'video_titles', $actor, ['status' => 'complete', 'status_override' => 'complete', 'note' => 'Published '.$items->count().' video titles for the uploader. Package '.$package->uuid.'.']);

            return $package;
        });
    }
}
