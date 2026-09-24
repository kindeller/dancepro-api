<?php

namespace App\Features\Tracking\Services;

use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaWatermark;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\UpdateConcertTrackerTask;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerSetting;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ConcertTracker
{
    public function __construct(
        private readonly ConcertTrackerTaskCatalog $catalog,
        private readonly UpdateConcertTrackerTask $tasks,
    ) {}

    public function page(string $view, int $year, string $phase, bool $attention = false): array
    {
        $definitions = $this->catalog->forPhase($phase);
        $events = $this->events($year);
        $sources = $this->sources($events);
        $overrides = $this->overrides($events);
        $rows = $events->map(fn (SchedulingEvent $event): array => $this->row($event, $definitions, $sources, $overrides));

        if ($view === 'studio') {
            $rows = $rows->sort(function (array $left, array $right): int {
                $studioOrder = strcasecmp($left['event']->studio?->name ?? 'zzzz', $right['event']->studio?->name ?? 'zzzz');

                return $studioOrder !== 0 ? $studioOrder : ($left['event']->event_date <=> $right['event']->event_date);
            })->values();
            $this->mergeSharedBookingCells($rows, $definitions);
        }

        $summary = $this->summary($rows);
        if ($attention) {
            $rows = $rows->filter(fn (array $row): bool => $row['tasks']->contains(fn (array $task): bool => $this->needsAttention($task)))->values();
        }

        $albums = MediaCollection::query()->where('media_type', 'photo')->where('status', 'draft')
            ->where('visibility', 'private')->whereNull('published_at')->whereDoesntHave('concertPhotoReleaseGroups')
            ->whereHas('concert.bookingItem.schedulingEvent', fn ($query) => $query->whereYear('event_date', $year))
            ->with('concert.studio')->orderByDesc('id')->get();
        $groups = ConcertPhotoReleaseGroup::with([
            'studio', 'watermark', 'coverAsset', 'collections.concert.bookingItem.schedulingEvent',
            'collections.assets' => fn ($query) => $query->where('media_type', 'photo')->where('status', 'available')
                ->where('is_visible', true)->where('metadata->is_top_pic', true)->orderBy('sort_order')->orderBy('id'),
        ])->whereHas('collections.concert.bookingItem.schedulingEvent', fn ($query) => $query->whereYear('event_date', $year))
            ->latest()->get();
        $watermarks = MediaWatermark::where('gallery_type', 'concert')->where('is_active', true)->orderBy('name')->get();
        $phases = ConcertTrackerTaskCatalog::PHASES;

        return compact('view', 'year', 'phase', 'attention', 'phases', 'definitions', 'rows', 'summary', 'albums', 'groups', 'watermarks');
    }

    public function detail(SchedulingEvent $event, string $taskKey): array
    {
        abort_unless($event->event_type->value === 'concert', 404);
        $definition = $this->catalog->find($taskKey);
        $event->load($this->relations());
        $sources = $this->sources(collect([$event]));
        $overrides = $this->overrides(collect([$event]));
        $status = $this->taskStatus($event, $definition, $sources, $overrides);
        $task = $this->tasks->task($event, $definition)->load(['assignee', 'completedBy', 'notes.user', 'deliveries.template']);
        $template = $definition['templateKey'] ? ConcertTrackerEmailTemplate::where('task_key', $definition['templateKey'])->first() : null;

        return compact('event', 'definition', 'status', 'task', 'template') + [
            'staff' => User::query()->where('is_admin', true)->where('is_active', true)->orderBy('name')->get(),
            'recipient' => $this->recipient($event),
            'renderedTemplate' => $template ? $this->renderTemplate($template->subject, $template->body, $event) : null,
        ];
    }

    public function renderTemplate(string $subject, string $body, SchedulingEvent $event): array
    {
        $event->loadMissing(['studio', 'venue', 'concertBookingItem.booking.items.schedulingEvent.roleRequirements.crewRole', 'concertBookingItem.booking.items.schedulingEvent.roleSeats.crewRole', 'concertBookingItem.booking.items.schedulingEvent.shifts', 'roleRequirements.crewRole', 'shifts.assignments.crewProfile.user']);
        $booking = $event->concertBookingItem?->booking;
        $item = $event->concertBookingItem;
        $video = (bool) $booking?->wants_concert_videography;
        $photo = (bool) $booking?->wants_concert_photography;
        $portraits = (bool) $booking?->wants_portrait_photography;
        $roleCodes = $event->roleRequirements->pluck('crewRole.code');
        $programme = Programme::query()->with('latestRevision')->where('scheduling_event_id', $event->id)->first();
        $slideshowComplete = ConcertTrackerTask::query()->where('scope_type', 'event')->where('scope_id', $event->id)
            ->where('task_key', 'slideshow_received')->where('status', 'complete')->exists();
        $conditions = [
            'video' => $video,
            'photo' => $photo,
            'photo_and_video' => $photo && $video,
            'photo_only' => $photo && ! $video,
            'portraits' => $portraits,
            'second_photographer' => $roleCodes->contains('concert-photographer-p2'),
            'programme_missing' => ! $programme?->latestRevision?->confirmed_at,
            'slideshow_missing' => $video && ! $slideshowComplete,
        ];
        $renderConditions = function (string $content) use ($conditions): string {
            $pattern = '/\{\{#if ([a-z_]+)\}\}((?:(?!\{\{#if ).)*?)\{\{\/if\}\}/s';

            while (preg_match($pattern, $content)) {
                $content = preg_replace_callback(
                    $pattern,
                    fn (array $match): string => ($conditions[$match[1]] ?? false) ? $match[2] : '',
                    $content,
                );
            }

            return $content;
        };
        $body = $renderConditions($body);
        $subject = $renderConditions($subject);
        $eventDetails = $booking?->items->sortBy('event_date')->map(function ($bookingItem) use ($photo, $video, $portraits): string {
            $dressRehearsal = $bookingItem->item_type === ConcertBookingItemType::DressRehearsal;
            $roleCodes = $bookingItem->schedulingEvent?->roleRequirements->pluck('crewRole.code') ?? collect();
            $services = $roleCodes->isNotEmpty()
                ? collect([
                    $roleCodes->contains(fn (?string $code): bool => in_array($code, ['photographer-p', 'concert-dr-portrait-assistant'], true)) ? 'portrait photography' : null,
                    $roleCodes->contains(fn (?string $code): bool => str_starts_with((string) $code, 'concert-photographer-')) ? 'photography' : null,
                    $roleCodes->contains('concert-videographer') ? 'videography' : null,
                ])->filter()->values()
                : collect($dressRehearsal
                    ? [$portraits ? 'portrait photography' : null]
                    : [$photo ? 'photography' : null, $video ? 'videography' : null])->filter()->values();
            $serviceText = ucfirst(match ($services->count()) {
                0 => 'To be confirmed',
                1 => $services->first(),
                2 => $services->join(' and '),
                default => $services->slice(0, -1)->join(', ').' and '.$services->last(),
            });
            $startLabel = $dressRehearsal ? 'Portrait session starts' : 'Curtain up';
            $title = trim((string) $bookingItem->title) ?: ($dressRehearsal ? 'Dress rehearsal portraits' : 'Concert');
            $access = $bookingItem->schedulingEvent?->shifts->pluck('posted_arrival_at')->filter()->sort()->first();
            if (! $access && $bookingItem->starts_at) {
                $access = Carbon::parse($bookingItem->starts_at)->subMinutes($services->contains('videography') ? 90 : 60);
            }
            $reservedSeats = $dressRehearsal ? null : $bookingItem->schedulingEvent?->roleSeats
                ->sortBy(fn ($allocation) => $allocation->crewRole?->name)
                ->map(function ($allocation): string {
                    $role = preg_replace('/^Concert\s+/i', '', (string) $allocation->crewRole?->name) ?: 'DancePro crew';
                    $seats = collect($allocation->seats)->map(fn (array $seat): string => 'Row '.$seat['row'].', Seat '.$seat['seat'])->join('; ');

                    return $role.': '.$seats;
                })->filter()->join(' | ');

            return collect([
                '**'.$title.'**',
                '',
                '- **Date:** '.($bookingItem->event_date?->format('l j F Y') ?? 'To be confirmed'),
                '- **Venue access/setup:** '.($access?->format('g:i a') ?? 'To be confirmed'),
                '- **'.$startLabel.':** '.($bookingItem->starts_at ? Carbon::parse($bookingItem->starts_at)->format('g:i a') : 'To be confirmed'),
                '- **Expected finish:** '.($bookingItem->finishes_at ? Carbon::parse($bookingItem->finishes_at)->format('g:i a') : 'To be confirmed'),
                '- **Venue:** '.($bookingItem->venue_name ?: 'To be confirmed'),
                $dressRehearsal ? null : '- **Reserved DancePro seats:** '.($reservedSeats ?: 'To be confirmed'),
                '- **Services:** '.$serviceText,
            ])->filter(fn (?string $line): bool => $line !== null)->implode("\n");
        })->implode("\n\n---\n\n") ?: '**'.$event->name."**\n\n- **Date:** ".($event->event_date?->format('l j F Y') ?? 'To be confirmed');
        $crewNames = $event->shifts->flatMap->assignments->map(fn ($assignment): ?string => $assignment->crewProfile?->preferred_name ?? $assignment->crewProfile?->user?->name)->filter()->unique()->implode(', ');
        $arrival = $event->shifts->pluck('posted_arrival_at')->filter()->sort()->first();
        $values = [
            '{{ studio_name }}' => $event->studio?->name ?? $booking?->studio_name ?? 'Your studio',
            '{{ event_name }}' => $event->name,
            '{{ event_date }}' => $event->event_date?->format('l j F Y') ?? 'the event date',
            '{{ event_code }}' => $event->eventCode() ?? '',
            '{{ contact_name }}' => $booking?->contact_name ?? $event->organiser_name ?? $event->studio?->contact_name ?? 'there',
            '{{ event_details }}' => $eventDetails,
            '{{ services }}' => ucfirst(collect([$photo ? 'photography' : null, $video ? 'videography' : null, $portraits ? 'portrait photography' : null])->filter()->join(', ', ' and ')),
            '{{ family_count }}' => (string) ($booking?->approximate_family_count ?? 'to be confirmed'),
            '{{ venue }}' => $item?->venue_name ?? $event->venue?->name ?? 'to be confirmed',
            '{{ start_time }}' => $item?->starts_at ? Carbon::parse($item->starts_at)->format('g:i a') : 'to be confirmed',
            '{{ finish_time }}' => $item?->finishes_at ? Carbon::parse($item->finishes_at)->format('g:i a') : 'to be confirmed',
            '{{ arrival_time }}' => $arrival?->format('g:i a') ?? 'to be confirmed',
            '{{ crew_names }}' => $crewNames ?: 'your DancePro team',
            '{{ gallery_access }}' => $event->studio?->gallery_code_hash ? 'Your studio gallery access is configured. Use the secure studio gallery instructions supplied by DancePro.' : 'Gallery access still needs to be configured for your studio.',
        ];

        return ['subject' => strtr($subject, $values), 'body' => preg_replace("/\n{3,}/", "\n\n", strtr($body, $values))];
    }

    private function events(int $year): Collection
    {
        return SchedulingEvent::query()->where('event_type', 'concert')->whereNull('cancelled_at')
            ->whereYear('event_date', $year)->with($this->relations())->orderBy('event_date')->orderBy('name')->get();
    }

    private function relations(): array
    {
        return [
            'studio', 'roleRequirements', 'shifts.assignments',
            'concertBookingItem.booking.items',
            'concertBookingItem.concert.mediaCollections' => fn ($query) => $query->withCount([
                'assets' => fn ($assets) => $assets->where('status', 'available')->where('is_visible', true),
            ]),
            'concertBookingItem.concert.mediaCollections.concertPhotoReleaseGroups',
        ];
    }

    private function sources(Collection $events): array
    {
        $programmes = Programme::query()->with(['latestRevision.confirmedBy', 'source.createdBy'])
            ->whereIn('scheduling_event_id', $events->pluck('id'))->get()->keyBy('scheduling_event_id');
        $galleries = ProgrammePhotoGallery::query()->whereIn('scheduling_event_id', $events->pluck('id'))->get()->keyBy('scheduling_event_id');
        $plans = $galleries->pluck('programme_upload_plan_id')->filter();
        $contributions = ProgrammePhotoContribution::query()->whereIn('programme_upload_plan_id', $plans)->get()->groupBy('programme_upload_plan_id');
        $files = ProgrammePhotoFile::query()->whereIn('programme_photo_gallery_id', $galleries->pluck('id'))
            ->where('status', 'verified')->whereNotNull('verified_at')->whereNotNull('storage_key')
            ->whereNull('duplicate_of_file_id')->get()->groupBy('programme_photo_gallery_id');

        return compact('programmes', 'galleries', 'contributions', 'files');
    }

    private function overrides(Collection $events): Collection
    {
        $bookingIds = $events->pluck('concertBookingItem.concert_booking_id')->filter()->unique();
        $studioIds = $events->pluck('studio_id')->filter()->unique();

        return ConcertTrackerTask::query()->with(['assignee', 'completedBy', 'notes', 'deliveries'])
            ->where(function ($query) use ($events, $bookingIds, $studioIds): void {
                $query->where(fn ($eventQuery) => $eventQuery->where('scope_type', 'event')->whereIn('scope_id', $events->pluck('id')))
                    ->orWhere(fn ($bookingQuery) => $bookingQuery->where('scope_type', 'booking')->whereIn('scope_id', $bookingIds))
                    ->orWhere(fn ($studioQuery) => $studioQuery->where('scope_type', 'studio')->whereIn('scope_id', $studioIds));
            })->get()->keyBy(fn (ConcertTrackerTask $task): string => $task->scope_type.':'.$task->scope_id.':'.$task->task_key);
    }

    private function row(SchedulingEvent $event, Collection $definitions, array $sources, Collection $overrides): array
    {
        $tasks = $definitions->mapWithKeys(fn (array $definition): array => [
            $definition['key'] => $this->taskStatus($event, $definition, $sources, $overrides),
        ]);

        return compact('event', 'tasks');
    }

    private function taskStatus(SchedulingEvent $event, array $definition, array $sources, Collection $overrides): array
    {
        [$scopeType, $scopeId] = $this->tasks->scope($event, $definition);
        $scopeKey = $scopeType.':'.$scopeId;
        $override = $overrides->get($scopeKey.':'.$definition['key']);
        $dueDate = $override?->due_date_override ?? $this->calculatedDueDate($event, $definition);
        $source = $definition['kind'] === 'source'
            ? $this->sourceStatus($event, $definition['key'], $sources)
            : $this->manualStatus($definition, $override);
        $isOpen = ! in_array($source['key'], ['complete', 'not_required'], true);
        if (! $isOpen || ($definition['key'] === 'programme_prepared' && $source['key'] === 'in_progress')) {
            $dueDate = null;
        }
        $overdue = $isOpen && $dueDate?->isBefore(today());
        $nearDue = $isOpen && ! $overdue && $dueDate?->lte(today()->addDays(7));
        $tone = $overdue ? 'overdue' : ($nearDue ? 'near-due' : $source['key']);
        $detailUrl = $definition['key'] === 'programme_prepared' && $source['sourceUrl']
            ? $source['sourceUrl']
            : route('admin.concert-tracker.tasks.show', ['schedulingEvent' => $event, 'taskKey' => $definition['key']]);

        return $source + compact('scopeKey', 'override', 'dueDate', 'overdue', 'nearDue', 'tone', 'detailUrl') + [
            'definition' => $definition,
            'assignee' => $override?->assignee,
            'note_count' => $override?->notes->count() ?? 0,
            'rowspan' => 1,
            'skip' => false,
        ];
    }

    private function sourceStatus(SchedulingEvent $event, string $key, array $sources): array
    {
        $booking = $event->concertBookingItem?->booking;
        $concert = $event->concertBookingItem?->concert;
        $collections = $concert?->mediaCollections ?? collect();
        $photoAlbums = $collections->filter(fn (MediaCollection $collection): bool => $collection->media_type->value === 'photo');
        $videoCollections = $collections->filter(fn (MediaCollection $collection): bool => $collection->media_type->value === 'video');
        $groups = $photoAlbums->flatMap->concertPhotoReleaseGroups->unique('id')->values();
        $programme = $sources['programmes']->get($event->id);
        $gallery = $sources['galleries']->get($event->id);
        $contributions = $gallery ? ($sources['contributions']->get($gallery->programme_upload_plan_id) ?? collect()) : collect();
        $files = $gallery ? ($sources['files']->get($gallery->id) ?? collect()) : collect();

        return match ($key) {
            'booking_received' => $booking
                ? $this->status($booking->status->value === 'approved' ? 'complete' : 'in_progress', $booking->status->value === 'approved' ? 'Approved' : 'Pending', 'Booking and selected services are held in the unified booking record.', route('admin.concert-bookings.show', $booking), $booking->reviewed_at)
                : $this->status('not_started', 'Not received', 'No concert booking is linked.', route('admin.concert-bookings.index')),
            'crew_ready' => $event->rosterIsReady()
                ? $this->status('complete', 'Ready', 'Required crew are published and acknowledged.', route('admin.scheduling-events.show', $event), $event->roster_published_at)
                : $this->status('not_started', 'Needs attention', 'Required crew or acknowledgements are incomplete.', route('admin.scheduling-events.show', $event)),
            'programme_prepared' => $programme?->latestRevision?->confirmed_at
                ? $this->status('complete', 'Confirmed', 'The working programme is confirmed.', route('admin.programmes.show', $event), $programme->latestRevision->confirmed_at,
                    'Confirmed '.$programme->latestRevision->confirmed_at->format('j M Y, g:i a').' by '.($programme->latestRevision->confirmedBy?->name ?? 'former staff member'))
                : (($programme?->source || $event->concertBookingItem?->programme_path || $event->programme_path)
                    ? $this->status('in_progress', 'Confirm', 'The programme is attached and still needs confirmation.', route('admin.programmes.show', $event), $programme?->source?->created_at,
                        $programme?->source ? 'Uploaded '.$programme->source->created_at->format('j M Y, g:i a').' by '.($programme->source->createdBy?->name ?? 'former staff member') : 'Attached to the event booking')
                    : $this->status('not_started', 'Not received', 'No programme source is attached.', route('admin.programmes.show', $event))),
            'video_uploaded' => $videoCollections->sum('assets_count') > 0
                ? $this->status('complete', $videoCollections->sum('assets_count').' uploaded', 'Available concert video media is attached.', route('admin.concerts.edit', $concert), $videoCollections->max('updated_at'))
                : $this->status('not_started', 'Not uploaded', 'No available concert video is attached.', $concert ? route('admin.concerts.edit', $concert) : route('admin.concerts.index')),
            'director_approval' => ! $concert
                ? $this->status('not_started', 'Not started', 'No customer concert is linked.', route('admin.concerts.index'))
                : ($concert->approved_at
                    ? $this->status('complete', 'Approved', 'Director approval is recorded.', route('admin.concerts.edit', $concert), $concert->approved_at)
                    : $this->status('waiting', 'Waiting', 'Director approval has not been recorded.', route('admin.concerts.edit', $concert))),
            'photos_uploaded' => ! $gallery
                ? $this->status('not_started', 'Not started', 'No uploader gallery exists yet.', route('admin.concert-photo-gallery.show', $event))
                : (($contributions->isNotEmpty() && ! $contributions->every(fn ($item): bool => $item->status === 'complete' || $item->waived_at !== null))
                    ? $this->status('waiting', 'Contributions open', $files->count().' verified photo(s); one or more photographer contributions remain open.', route('admin.concert-photo-gallery.show', $event))
                    : ($files->isNotEmpty()
                        ? $this->status('complete', $files->count().' verified', 'Required contributions and verified uploads are complete.', route('admin.concert-photo-gallery.show', $event), $files->max('verified_at'))
                        : $this->status('waiting', 'Waiting for files', 'The uploader gallery exists but has no verified photos.', route('admin.concert-photo-gallery.show', $event)))),
            'photo_release' => $this->photoReleaseStatus($event, $concert, $photoAlbums, $groups),
            'final_release' => $concert?->status->value === 'published' && $concert->published_at
                ? $this->status('complete', 'Released', 'The concert video record is published.', route('admin.concerts.edit', $concert), $concert->published_at)
                : $this->status('not_started', 'Not released', 'The final concert video is not published.', $concert ? route('admin.concerts.edit', $concert) : route('admin.concerts.index')),
            default => $this->status('not_started', 'Not started', 'Open the task to update it.', route('admin.scheduling-events.show', $event)),
        };
    }

    private function photoReleaseStatus(SchedulingEvent $event, mixed $concert, Collection $albums, Collection $groups): array
    {
        if (! $concert) {
            return $this->status('not_started', 'Link concert', 'Link the customer concert record.', route('admin.concerts.index'));
        }
        if ($albums->isEmpty()) {
            return $this->status('not_started', 'Not started', 'No customer photo albums exist.', route('admin.concert-photo-gallery.show', $event));
        }
        if ($albums->every(fn (MediaCollection $album): bool => $album->published_at !== null)) {
            return $this->status('complete', 'Released', 'All photo albums are released.', route('admin.concert-tracker.index', ['year' => $event->event_date?->year, 'phase' => 'completion']).'#release-groups', $groups->max('released_at'));
        }
        if ($groups->contains(fn (ConcertPhotoReleaseGroup $group): bool => $group->released_at === null)) {
            return $this->status('in_progress', 'Ready to release', 'The parent album is ready for release.', route('admin.concert-tracker.index', ['year' => $event->event_date?->year, 'phase' => 'completion']).'#release-groups');
        }

        return $this->status('not_started', 'Create group', 'Create the parent release group.', route('admin.concert-tracker.index', ['year' => $event->event_date?->year, 'phase' => 'completion']).'#prepare-release');
    }

    private function manualStatus(array $definition, ?ConcertTrackerTask $override): array
    {
        $delivery = $override?->deliveries->first();
        if ($override?->status !== 'complete' && $delivery) {
            $deliveryStatus = match ($delivery->status) {
                'scheduled' => ['in_progress', 'Scheduled', 'Scheduled for '.$delivery->scheduled_at?->format('j M Y g:i a').'.'],
                'queued' => ['in_progress', 'Queued', 'Queued for delivery.'],
                'failed' => ['failed', 'Failed', 'Delivery failed. Open the task to review and resend it.'],
                'cancelled' => ['waiting', 'Cancelled', 'The scheduled delivery was cancelled.'],
                default => null,
            };
            if ($deliveryStatus) {
                return $this->status(...$deliveryStatus, completedAt: null, sourceUrl: null);
            }
        }
        $key = $override?->status ?? ($definition['optional'] ? 'not_required' : 'not_started');
        $label = match ($key) {
            'complete' => 'Complete', 'in_progress' => 'In progress', 'waiting' => 'Waiting',
            'not_required' => 'Not required', default => 'Not started',
        };
        $detail = $override?->completed_at
            ? 'Completed '.$override->completed_at->format('j M Y g:i a').($override->completedBy ? ' by '.$override->completedBy->name : '').'.'
            : ($override?->assignee ? 'Assigned to '.$override->assignee->name.'.' : 'Open the task to update its status.');

        return $this->status($key, $label, $detail, null, $override?->completed_at);
    }

    private function calculatedDueDate(SchedulingEvent $event, array $definition): ?CarbonInterface
    {
        if (in_array($definition['key'], ['concert_edited', 'quality_check', 'director_approval'], true)) {
            $settings = ConcertTrackerSetting::query()->first();
            $days = $definition['key'] === 'director_approval'
                ? ($settings?->approval_target_days ?? 14)
                : ($settings?->editing_target_days ?? 14);

            return $event->event_date?->copy()->addDays($days);
        }
        if ($definition['dueBasis'] === null || $definition['dueOffsetDays'] === null) {
            return null;
        }
        $bookingDates = $event->concertBookingItem?->booking?->items->pluck('event_date')->filter()->sort();
        $base = match ($definition['dueBasis']) {
            'booking_first' => $bookingDates?->first(),
            'booking_last' => $bookingDates?->last(),
            default => $event->event_date,
        };

        return $base?->copy()->addDays($definition['dueOffsetDays']);
    }

    private function status(string $key, string $label, string $detail, ?string $sourceUrl, ?CarbonInterface $completedAt = null, ?string $activity = null): array
    {
        return compact('key', 'label', 'detail', 'sourceUrl', 'completedAt', 'activity');
    }

    private function mergeSharedBookingCells(Collection $rows, Collection $definitions): void
    {
        foreach ($definitions->where('scope', 'booking') as $definition) {
            $start = 0;
            while ($start < $rows->count()) {
                $scopeKey = $rows[$start]['tasks'][$definition['key']]['scopeKey'];
                $end = $start + 1;
                while ($end < $rows->count() && $rows[$end]['tasks'][$definition['key']]['scopeKey'] === $scopeKey
                    && $rows[$end]['event']->studio_id === $rows[$start]['event']->studio_id) {
                    $end++;
                }
                $span = $end - $start;
                $firstRow = $rows->get($start);
                $firstTask = $firstRow['tasks']->get($definition['key']);
                $firstTask['rowspan'] = $span;
                $firstRow['tasks']->put($definition['key'], $firstTask);
                $rows->put($start, $firstRow);
                for ($index = $start + 1; $index < $end; $index++) {
                    $sharedRow = $rows->get($index);
                    $sharedTask = $sharedRow['tasks']->get($definition['key']);
                    $sharedTask['skip'] = true;
                    $sharedRow['tasks']->put($definition['key'], $sharedTask);
                    $rows->put($index, $sharedRow);
                }
                $start = $end;
            }
        }
    }

    private function summary(Collection $rows): array
    {
        $tasks = $rows->flatMap(fn (array $row) => $row['tasks'])->unique(fn (array $task): string => $task['scopeKey'].':'.$task['definition']['key']);

        return [
            'attention' => $tasks->filter(fn (array $task): bool => $this->needsAttention($task))->count(),
            'near_due' => $tasks->where('nearDue', true)->count(),
            'complete' => $tasks->where('key', 'complete')->count(),
            'open' => $tasks->reject(fn (array $task): bool => in_array($task['key'], ['complete', 'not_required'], true))->count(),
        ];
    }

    private function needsAttention(array $task): bool
    {
        return $task['overdue'] || $task['key'] === 'failed'
            || ($task['override']?->follow_up_date?->lte(today()) && ! in_array($task['key'], ['complete', 'not_required'], true));
    }

    private function recipient(SchedulingEvent $event): array
    {
        $booking = $event->concertBookingItem?->booking;

        return [
            'name' => $booking?->contact_name ?? $event->organiser_name ?? $event->studio?->contact_name,
            'email' => $booking?->contact_email ?? $event->organiser_email ?? $event->studio?->contact_email,
        ];
    }
}
