<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitionGallery
{
    public function __construct(
        private readonly CompetitionProgrammeNodes $programmeNodes,
        private readonly ProgrammePhotoPreview $previews,
        private readonly CompetitionSectionReadiness $readiness,
        private readonly CompetitionGallerySettings $settings,
        private readonly CompetitionFolderCovers $folderCovers,
    ) {}

    public function events(): array
    {
        $events = SchedulingEvent::with('competitionContact')->where('event_type', 'competition')
            ->whereNull('cancelled_at')->orderByDesc('event_date')->orderByDesc('id')->paginate(30)->withQueryString();
        $presentation = $this->settings->forEvents($events->getCollection());

        return compact('events', 'presentation');
    }

    public function browse(SchedulingEvent $event, ?string $folder, bool $withFolderCovers = true): array
    {
        abort_unless($event->event_type->value === 'competition', 404);
        $programme = Programme::where('scheduling_event_id', $event->id)->first();
        $revision = $programme?->revisions()->orderByDesc('version')->first();
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $plan = $gallery ? ProgrammeUploadPlan::findOrFail($gallery->programme_upload_plan_id)
            : ($revision ? ProgrammeUploadPlan::where('programme_revision_id', $revision->id)->first() : null);
        $snapshot = $plan?->snapshot ?? $revision?->snapshot ?? [];
        $nodes = collect($plan ? $this->programmeNodes->nodes($plan) : ($snapshot['nodes'] ?? []))
            ->filter(fn ($node) => empty($node['merged_into_uuid']) && in_array($node['kind'], ['day', 'section', 'item'])
                && ($node['item_kind'] ?? 'dance') !== 'break')->keyBy('uuid');
        $days = $nodes->where('kind', 'day')->sortBy('date')->values();
        $showDays = $days->count() > 1;
        $nodes = $nodes->map(function ($node) use ($days) {
            $node['label'] = $node['kind'] === 'day'
                ? sprintf('%02d', $days->search(fn ($day) => $day['uuid'] === $node['uuid']) + 1).' '.CarbonImmutable::parse($node['date'])->locale('en')->dayName
                : $this->label($node);

            return $node;
        });
        $selected = $folder ? $nodes->get($folder) : null;
        abort_if($folder && (! $selected || (! $showDays && $selected['kind'] === 'day')), 404);
        $children = $nodes->filter(fn ($node) => $selected
            ? ($node['parent_uuid'] ?? null) === $selected['uuid']
            : $node['kind'] === ($showDays ? 'day' : 'section'))->values()->all();
        usort($children, fn ($a, $b) => $a['kind'] === 'day' ? strcmp($a['date'], $b['date']) : strnatcasecmp($a['number'] ?? '', $b['number'] ?? ''));
        if ($withFolderCovers) {
            $children = $this->folderCovers->apply($children, $nodes, $event);
        }
        $breadcrumbs = [];
        $ancestor = $selected;
        while ($ancestor) {
            if ($showDays || $ancestor['kind'] !== 'day') {
                array_unshift($breadcrumbs, $ancestor);
            }
            $ancestor = $nodes->get($ancestor['parent_uuid'] ?? '');
        }
        $photos = $selected && $selected['kind'] === 'item' && $gallery
            ? $this->files($gallery)->where('contributions.item_uuid', $selected['uuid'])
                ->orderBy('programme_photo_files.id')->paginate(48)->withQueryString() : null;
        $eventCode = $snapshot['event_code'] ?? $event->eventCode() ?? $event->name;
        $stale = $plan && $revision && $plan->programme_revision_id !== $revision->id;

        $sectionReadiness = $plan ? $this->readiness->forPlan($event, $plan) : [];
        $selectedSection = $selected ? ($selected['kind'] === 'section' ? $selected['uuid'] : ($selected['kind'] === 'item' ? $selected['parent_uuid'] : null)) : null;

        $presentation = $this->settings->presentation($event);

        return compact('nodes', 'presentation', 'sectionReadiness', 'selectedSection', 'event', 'eventCode', 'selected', 'children', 'breadcrumbs', 'photos', 'stale');
    }

    public function image(SchedulingEvent $event, string $uuid, string $size = 'preview'): StreamedResponse
    {
        abort_unless($event->event_type->value === 'competition', 404);
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->firstOrFail();
        $file = $this->files($gallery)->where('programme_photo_files.uuid', $uuid)->firstOrFail();
        // The current transport stores verified originals only on the private local disk.
        abort_unless($file->storage_disk === 'local' && Storage::disk('local')->exists($file->storage_key), 404);

        return Storage::disk('local')->response($this->previews->key($file, $size, $event), $file->uuid.'-'.$size.'.jpg', [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function files(ProgrammePhotoGallery $gallery): Builder
    {
        return ProgrammePhotoFile::inGallery($gallery);
    }

    private function label(array $node): string
    {
        $number = preg_replace_callback('/^([0-9]+)(.*)$/', fn ($match) => str_pad(ltrim($match[1], '0') ?: '0', 2, '0', STR_PAD_LEFT).$match[2], $node['number'] ?? '');

        return trim($number.' '.$node['title']);
    }
}
