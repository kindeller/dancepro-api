<?php

namespace App\Features\Tracking\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Services\BookingSeatsAndFamilies;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Concerts\Models\ConcertDirectorReview;
use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\Customers\Services\StudioHubLoginCodes;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaWatermark;
use App\Features\Media\Services\ConcertPhotoReleases;
use App\Features\Programmes\Models\ConcertTitleCardPackage;
use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\ConcertVideoBatch;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Services\ConcertTitlePackages;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\EventRosterStatus;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Services\InvoiceTrackerWorkflow;
use App\Features\Tracking\Actions\UpdateConcertTrackerTask;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerSetting;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConcertTracker
{
    public function __construct(
        private readonly ConcertTrackerTaskCatalog $catalog,
        private readonly UpdateConcertTrackerTask $tasks,
        private readonly BookingTrackerBar $bookingBar,
        private readonly BookingSeatsAndFamilies $seatsAndFamilies,
    ) {}

    public function page(string $view, int $year, string $phase, bool $attention = false): array
    {
        $definitions = $this->catalog->forPhase($phase);
        $rows = $this->rowsForYear($year);
        $yearSummary = $this->summary($rows);
        request()->attributes->set('concert_tracker_attention_'.$year, $yearSummary['attention']);
        Cache::put('concert-tracker-attention:v2:'.$year, $yearSummary['attention'], now()->addSeconds(30));

        $rows = $rows->filter(fn (array $row): bool => ! $row['pending'] || $definitions->contains('key', 'booking_received'))
            ->map(function (array $row) use ($definitions): array {
                $row['tasks'] = $row['tasks']->only($definitions->pluck('key')->all());

                return $row;
            })->values();
        $pendingCount = $rows->where('pending', true)->count();

        if ($view === 'studio') {
            $rows = $rows->sort(function (array $left, array $right): int {
                $studioOrder = strcasecmp($left['pending'] ? $left['name'] : ($left['event']->studio?->name ?? 'zzzz'), $right['pending'] ? $right['name'] : ($right['event']->studio?->name ?? 'zzzz'));

                return $studioOrder !== 0 ? $studioOrder : ($left['event_date'] <=> $right['event_date']);
            })->values();
        }

        $summary = $this->summary($rows);
        if ($attention) {
            $rows = $rows->filter(fn (array $row): bool => $row['pending'] || $row['tasks']->contains(fn (array $task): bool => $this->needsAttention($task)))->values();
        }

        if ($view === 'studio') {
            $this->mergeSharedBookingCells($rows, $definitions);
        }

        $codes = app(StudioHubLoginCodes::class);
        $galleryLogins = $rows->map(fn (array $row) => $row['pending'] ? $row['bookingItem']->booking->studio : $row['event']->studio)
            ->filter()->unique('id')->mapWithKeys(fn ($studio): array => [$studio->id => $codes->photo($studio)]);
        $releaseLogins = $rows->reject(fn (array $row): bool => $row['pending'])->mapWithKeys(function (array $row) use ($codes, $galleryLogins): array {
            $event = $row['event'];
            $concert = $event->concertBookingItem?->concert;

            return [$event->id => [
                'photo_release' => $galleryLogins->get($event->studio_id),
                'final_release' => $event->studio && $row['tasks']->has('final_release') && $this->videoReleased($event, $concert)
                    ? $codes->video($concert, $event->studio) : null,
            ]];
        });

        $phases = ConcertTrackerTaskCatalog::PHASES;

        return compact('view', 'year', 'phase', 'attention', 'phases', 'definitions', 'rows', 'summary', 'pendingCount', 'galleryLogins', 'releaseLogins');
    }

    public function notificationCount(int $year): int
    {
        if (request()->attributes->has('concert_tracker_attention_'.$year)) {
            return request()->attributes->get('concert_tracker_attention_'.$year);
        }

        // Other admin pages need only a count; opening the tracker always refreshes it.
        return Cache::remember('concert-tracker-attention:v2:'.$year, now()->addSeconds(30),
            fn (): int => $this->summary($this->rowsForYear($year))['attention']);
    }

    private function rowsForYear(int $year): Collection
    {
        $events = $this->events($year);
        $sources = $this->sources($events);
        $overrides = $this->overrides($events);
        $definitions = $this->catalog->all();

        return $events->map(fn (SchedulingEvent $event): array => $this->row($event, $definitions, $sources, $overrides))
            ->concat($this->pendingRows($year))->sortBy([['event_date', 'asc'], ['name', 'asc']])->values();
    }

    public function statusesForEvents(Collection $events): Collection
    {
        (new \Illuminate\Database\Eloquent\Collection($events->all()))->loadMissing($this->relations());
        $sources = $this->sources($events);
        $overrides = $this->overrides($events);

        return $events->mapWithKeys(fn (SchedulingEvent $event): array => [
            $event->id => $this->catalog->all()->mapWithKeys(fn (array $definition): array => [
                $definition['key'] => $this->taskStatus($event, $definition, $sources, $overrides),
            ]),
        ]);
    }

    public function detail(SchedulingEvent $event, string $taskKey): array
    {
        abort_unless($event->event_type->value === 'concert', 404);
        $definition = $this->catalog->find($taskKey);
        $event->load($this->relations());
        $sources = $this->sources(collect([$event]));
        $overrides = $this->overrides(collect([$event]));
        $status = $this->taskStatus($event, $definition, $sources, $overrides);
        $task = $this->tasks->task($event, $definition)->load(['assignee', 'completedBy', 'notes.user', 'deliveries.template', 'deliveries.createdBy']);
        $template = $definition['templateKey'] ? ConcertTrackerEmailTemplate::where('task_key', $definition['templateKey'])->first() : null;

        return compact('event', 'definition', 'status', 'task', 'template') + [
            'releaseWorkspace' => $taskKey === 'photo_release' ? $this->photoReleaseWorkspace($event) : null,
            'staff' => User::query()->where('is_admin', true)->where('is_active', true)->orderBy('name')->get(),
            'recipient' => $this->recipient($event),
            'renderedTemplate' => $template ? $this->renderTemplate($template->subject, $template->body, $event) : null,
        ];
    }

    private function photoReleaseWorkspace(SchedulingEvent $event): array
    {
        // Unassigned events must not expose unrelated studios' release work.
        if (! $event->studio_id) {
            return ['albums' => collect(), 'groups' => collect(), 'watermarks' => collect()];
        }
        $year = $event->event_date?->year;
        $albums = MediaCollection::query()->where('media_type', 'photo')->where('status', 'draft')
            ->where('visibility', 'private')->whereNull('published_at')->whereDoesntHave('concertPhotoReleaseGroups')
            ->whereHas('concert', fn ($query) => $query->where('studio_id', $event->studio_id))
            ->whereHas('concert.bookingItem.schedulingEvent', fn ($query) => $query->whereYear('event_date', $year))
            ->with('concert.studio')->orderByDesc('id')->get();
        $groups = ConcertPhotoReleaseGroup::with([
            'studio', 'watermark', 'coverAsset', 'collections.concert.bookingItem.schedulingEvent',
            'collections.assets' => fn ($query) => $query->where('media_type', 'photo')->where('status', 'available')
                ->where('is_visible', true)->where('metadata->is_top_pic', true)->orderBy('sort_order')->orderBy('id'),
        ])->where('studio_id', $event->studio_id)
            ->whereHas('collections.concert.bookingItem.schedulingEvent', fn ($query) => $query->whereYear('event_date', $year))
            ->latest()->get();
        $watermarks = MediaWatermark::where('gallery_type', 'concert')->where('is_active', true)->orderBy('name')->get();

        $releaseBlockers = $groups->mapWithKeys(fn ($group) => [$group->id => $group->released_at ? [] : app(ConcertPhotoReleases::class)->releaseBlockers($group)]);

        return compact('albums', 'groups', 'watermarks', 'releaseBlockers');
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
        $eventDetails = $booking?->items->whereNull('cancelled_at')->sortBy('event_date')->map(function ($bookingItem) use ($photo, $video, $portraits): string {
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
        $invoice = $booking ? StudioInvoice::where('active_booking_id', $booking->id)->first() : null;
        $values = [
            '{{ studio_name }}' => $event->studio?->name ?? $booking?->studio_name ?? 'Your studio',
            '{{ event_name }}' => $event->name,
            '{{ event_date }}' => $event->event_date?->format('l j F Y') ?? 'the event date',
            '{{ event_code }}' => $event->eventCode() ?? '',
            '{{ contact_name }}' => $booking?->contact_name ?? $event->organiser_name ?? $event->studio?->contact_name ?? 'there',
            '{{ event_details }}' => $eventDetails,
            '{{ services }}' => ucfirst(collect([$photo ? 'photography' : null, $video ? 'videography' : null, $portraits ? 'portrait photography' : null])->filter()->join(', ', ' and ')),
            '{{ family_count }}' => (string) ($booking?->final_family_count ?? $booking?->approximate_family_count ?? 'to be confirmed'),
            '{{ venue }}' => $item?->venue_name ?? $event->venue?->name ?? 'to be confirmed',
            '{{ start_time }}' => $item?->starts_at ? Carbon::parse($item->starts_at)->format('g:i a') : 'to be confirmed',
            '{{ finish_time }}' => $item?->finishes_at ? Carbon::parse($item->finishes_at)->format('g:i a') : 'to be confirmed',
            '{{ arrival_time }}' => $arrival?->format('g:i a') ?? 'to be confirmed',
            '{{ crew_names }}' => $crewNames ?: 'your DancePro team',
            '{{ review_url }}' => route('customer.concert-review.show', $event),
            '{{ invoice_url }}' => $invoice ? route('customer.studio-invoices.show', [$invoice->studio, $invoice]) : ($event->studio ? route('customer.studio-invoices.index', $event->studio) : route('customer.media.index')),
            '{{ gallery_url }}' => $event->studio ? route('customer.profile.studio', $event->studio) : route('customer.media.index'),
            '{{ gallery_access }}' => $event->studio?->gallery_code_hash ? 'Your studio gallery access is configured. Use the secure studio gallery instructions supplied by DancePro.' : 'Gallery access still needs to be configured for your studio.',
        ];

        return ['subject' => strtr($subject, $values), 'body' => preg_replace("/\n{3,}/", "\n\n", strtr($body, $values))];
    }

    private function events(int $year): Collection
    {
        return SchedulingEvent::query()->where('event_type', 'concert')->whereNull('cancelled_at')
            ->whereYear('event_date', $year)->with($this->relations())->orderBy('event_date')->orderBy('name')->get();
    }

    private function pendingRows(int $year): Collection
    {
        $items = ConcertBookingItem::query()
            ->awaitingApproval()
            ->whereYear('event_date', $year)
            ->with('booking.studio')
            ->get();
        $tasks = ConcertTrackerTask::query()->with('assignee')->where('scope_type', 'booking')
            ->where('task_key', 'booking_received')->whereIn('scope_id', $items->pluck('concert_booking_id'))->get()->keyBy('scope_id');

        return $items->map(fn (ConcertBookingItem $item): array => [
            'event' => null,
            'pending' => true,
            'bookingItem' => $item,
            'bookingTask' => $tasks->get($item->concert_booking_id),
            'bookingStatus' => $this->bookingBar->source($item->booking, $tasks->get($item->concert_booking_id)),
            'event_date' => $item->event_date,
            'name' => $item->booking->studio?->name ?? $item->booking->studio_name,
            'tasks' => collect(),
        ]);
    }

    private function relations(): array
    {
        return [
            'studio', 'roleRequirements', 'shifts.assignments.role', 'shifts.assignments.crewProfile.user', 'studioFiles.uploadedBy', 'studioFiles.reviewedBy',
            'concertBookingItem.booking.items.schedulingEvent.roleRequirements.crewRole',
            'concertBookingItem.booking.items.schedulingEvent.roleSeats.crewRole',
            'concertBookingItem.booking.items.schedulingEvent.roleSeats.submittedBy', 'concertBookingItem.booking.familyCountConfirmedBy',
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
        $invoices = StudioInvoice::with('payments', 'sentBy')
            ->whereIn('active_booking_id', $events->pluck('concertBookingItem.concert_booking_id')->filter())->get()->keyBy('active_booking_id');

        $titlePackages = ConcertTitleCardPackage::whereIn('id', fn ($query) => $query->from('concert_title_card_packages')->selectRaw('MAX(id)')->whereIn('scheduling_event_id', $events->pluck('id'))->groupBy('scheduling_event_id'))->get()->keyBy('scheduling_event_id');
        $titleTemplates = ConcertTitleCardTemplate::whereIn('scheduling_event_id', $events->pluck('id'))->get()->keyBy('scheduling_event_id');

        return compact('programmes', 'invoices', 'titlePackages', 'titleTemplates');
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

        return compact('event', 'tasks') + [
            'pending' => false,
            'event_date' => $event->event_date,
            'name' => $event->name,
        ];
    }

    private function taskStatus(SchedulingEvent $event, array $definition, array $sources, Collection $overrides): array
    {
        [$scopeType, $scopeId] = $this->tasks->scope($event, $definition);
        $scopeKey = $scopeType.':'.$scopeId;
        $override = $overrides->get($scopeKey.':'.$definition['key']);
        $dueDate = $override?->due_date_override ?? $this->calculatedDueDate($event, $definition);
        if ($definition['key'] === 'invoice_sent') {
            $invoice = $sources['invoices']->get($event->concertBookingItem?->concert_booking_id);
            $dueDate = $invoice?->sent_at ? $invoice->due_date : ($event->concertBookingItem?->booking?->family_count_confirmed_at ? $dueDate : null);
        }
        $source = $definition['kind'] === 'source'
            ? $this->sourceStatus($event, $definition['key'], $sources)
            : $this->manualStatus($definition, $override);
        if ($definition['key'] === 'quality_check') {
            $quality = app(ConcertQualityChecklist::class)->summary($override);
            $source = $this->status($quality['complete'] ? 'complete' : ($quality['checked'] ? 'in_progress' : 'not_started'),
                $quality['checked'].' / '.$quality['total'].' checked', 'Review every quality check before sending the concert to the studio.',
                route('admin.concert-tracker.tasks.show', [$event, 'quality_check']), $quality['complete'] ? $override?->completed_at : null);
        }
        if ($definition['key'] === 'booking_received' && $event->concertBookingItem?->booking) {
            $source = $this->bookingBar->source($event->concertBookingItem->booking, $override);
        }
        if ($definition['key'] === 'video_titles' && $source['key'] === 'complete'
            && ! app(ConcertTitlePackages::class)->matches($event, $sources['titlePackages']->get($event->id), $sources['programmes']->get($event->id), $sources['titleTemplates']->get($event->id))) {
            $source = $this->status('in_progress', 'Titles need updating', 'The programme or template changed. Publish the video titles again.', null);
        }
        if ($definition['key'] === 'slideshow_received') {
            $source['sourceUrl'] = route('admin.scheduling-events.show', $event).'#studio-submissions';
            $studioVideos = $event->studioFiles->where('is_current', true)->where('kind', 'studio_video');
            if ($studioVideos->isNotEmpty()) {
                $approved = $studioVideos->where('status', 'approved')->count();
                $source = $this->status(
                    $approved === $studioVideos->count() ? 'complete' : 'in_progress',
                    $approved === $studioVideos->count() ? 'Approved' : 'Review files',
                    $approved.' of '.$studioVideos->count().' studio videos approved.',
                    $source['sourceUrl'],
                    $approved === $studioVideos->count() ? $studioVideos->max('reviewed_at') : null,
                    $this->studioMediaActivity($studioVideos),
                );
            }
        }
        $notRequired = $this->notRequiredReason($event, $definition);
        if ($notRequired) {
            $source = $this->status('not_required', 'Not required', $notRequired, route('admin.scheduling-events.show', $event));
        }
        $sourceDriven = $definition['kind'] === 'source' || $definition['key'] === 'quality_check' || $notRequired !== null
            || ($definition['key'] === 'slideshow_received' && $studioVideos->isNotEmpty());
        $isOpen = ! in_array($source['key'], ['complete', 'not_required'], true);
        if (! $isOpen || ($definition['key'] === 'programme_prepared' && $source['key'] === 'in_progress')) {
            $dueDate = null;
        }
        $overdue = $isOpen && ($definition['key'] === 'photo_release' ? $dueDate?->isPast() : $dueDate?->isBefore(today()));
        $nearDue = $isOpen && ! $overdue && $dueDate?->lte(today()->addDays(3));
        $baseTone = $definition['key'] === 'final_numbers_received' && $source['key'] === 'waiting' ? 'in_progress' : $source['key'];
        if ($definition['key'] === 'video_uploaded' && $isOpen) {
            $baseTone = 'ready';
        }
        if ($definition['key'] === 'invoice_sent' && $source['label'] === 'Pending') {
            $baseTone = 'ready';
        }
        $tone = $overdue ? 'overdue' : ($nearDue ? 'near-due' : $baseTone);
        if ($definition['key'] === 'director_corrections') {
            $tone = match ($source['label']) {
                'Pending' => 'ready', 'Corrections' => 'overdue', default => $baseTone,
            };
        }
        if ($definition['key'] === 'invoice_sent' && $source['label'] === 'Not paid') {
            $tone = $overdue ? 'overdue' : 'near-due';
        }
        if ($definition['key'] === 'photo_release' && $source['label'] === 'Ready to release') {
            $tone = 'overdue';
        }
        if ($definition['key'] === 'crew_ready') {
            $tone = $source['key'] === 'complete' ? 'complete' : ($source['key'] === 'not_required' ? 'not_required' : 'roster');
        }
        if ($definition['key'] === 'photos_uploaded' && $source['key'] !== 'not_required') {
            $nearDue = false;
            $tone = $source['key'] === 'complete' ? 'complete' : ($overdue && $source['key'] === 'waiting' ? 'overdue' : 'ready');
            foreach ($source['photoRoles'] as &$role) {
                $role['tone'] = $role['state'] === 'complete' ? 'complete' : ($role['state'] === 'waiting' && $overdue ? 'overdue' : 'ready');
            }
            unset($role);
        }
        $detailUrl = in_array($definition['key'], ['programme_prepared', 'booking_received', 'final_numbers_received', 'photos_uploaded'], true) && $source['sourceUrl']
            ? $source['sourceUrl']
            : route('admin.concert-tracker.tasks.show', ['schedulingEvent' => $event, 'taskKey' => $definition['key']]);

        if ($definition['key'] === 'video_titles') {
            $detailUrl = $event->concertBookingItem?->booking
                ? route('admin.concert-bookings.show', [$event->concertBookingItem->booking, 'tab' => 'video-titles', 'event' => $event->uuid])
                : route('admin.concert-title-cards.show', $event);
            $source['sourceUrl'] = $detailUrl;
        }
        if ($definition['key'] === 'programme_prepared' && $event->concertBookingItem?->booking) {
            $detailUrl = route('admin.concert-bookings.show', [$event->concertBookingItem->booking, 'tab' => 'programme', 'event' => $event->uuid]);
        }
        if ($definition['key'] === 'slideshow_received' && $event->concertBookingItem?->booking) {
            $detailUrl = route('admin.concert-bookings.show', [$event->concertBookingItem->booking, 'tab' => 'studio-media', 'event' => $event->uuid]);
            $source['sourceUrl'] = $detailUrl;
        }
        if ($definition['key'] === 'final_numbers_received') {
            $scopeKey .= ':event:'.$event->id;
        }

        return $source + compact('scopeKey', 'override', 'dueDate', 'overdue', 'nearDue', 'tone', 'detailUrl', 'sourceDriven') + [
            'definition' => $definition,
            'assignee' => $override?->assignee,
            'note_count' => $override?->notes->count() ?? 0,
            'rowspan' => 1,
            'skip' => false,
        ];
    }

    private function notRequiredReason(SchedulingEvent $event, array $definition): ?string
    {
        $item = $event->concertBookingItem;
        $booking = $item?->booking;
        if ($booking && in_array($definition['key'], ['invoice_sent', 'payment_received'], true) && ! $booking->wants_concert_videography
            && ! StudioInvoice::where('active_booking_id', $booking->id)->exists()) {
            return 'No studio invoice is required unless reviewed custom charges are added.';
        }
        if ($booking && $definition['service']) {
            $portraits = $item->item_type === ConcertBookingItemType::DressRehearsal;
            $booked = $definition['service'] === 'video'
                ? (! $portraits && $booking->wants_concert_videography)
                : ($portraits ? $booking->wants_portrait_photography : $booking->wants_concert_photography);
            if (! $booked) {
                return ucfirst($definition['service']).' service is not booked for this event. Review the booking if this changes.';
            }
        }
        if (in_array($definition['key'], ['approval_link_email', 'director_corrections', 'director_approval'], true)
            && $item?->concert && ! $item->concert->requires_approval) {
            return 'Director approval is disabled for this concert.';
        }

        return null;
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

        return match ($key) {
            'invoice_sent', 'payment_received' => $this->invoiceStatus($booking, $sources['invoices']->get($booking?->id), $key),
            'booking_received' => $booking
                ? $this->bookingBar->source($booking, null)
                : $this->status('not_started', 'Not received', 'No concert booking is linked.', route('admin.concert-bookings.index')),
            'final_numbers_received' => $this->seatsAndFamilies->status($event),
            'crew_ready' => $this->crewStatus($event),
            'programme_prepared' => $programme?->latestRevision?->confirmed_at
                ? $this->status('complete', 'Confirmed', 'The working programme is confirmed.', route('admin.programmes.show', $event), $programme->latestRevision->confirmed_at,
                    'Confirmed '.$programme->latestRevision->confirmed_at->format('j M Y, g:i a').' by '.($programme->latestRevision->confirmedBy?->name ?? 'former staff member'))
                : (($programme?->source || $event->concertBookingItem?->programme_path || $event->programme_path)
                    ? $this->status('in_progress', 'Confirm', 'The programme is attached and still needs confirmation.', route('admin.programmes.show', $event), $programme?->source?->created_at,
                        $programme?->source ? 'Uploaded '.$programme->source->created_at->format('j M Y, g:i a').' by '.($programme->source->createdBy?->name ?? 'former staff member') : 'Attached to the event booking')
                    : $this->status('not_started', 'Not received', 'No programme source is attached.', route('admin.programmes.show', $event))),
            'video_uploaded' => app(ConcertVideoReadiness::class)->status($event),
            'director_corrections' => $this->correctionsStatus($event),
            'director_approval' => ! $concert
                ? $this->status('not_started', 'Not started', 'No customer concert is linked.', route('admin.concerts.index'))
                : ($concert->approved_at
                    ? $this->status('complete', 'Approved', 'Director approval is recorded.', route('admin.concerts.edit', $concert), $concert->approved_at)
                    : $this->status('waiting', 'Waiting', 'Director approval has not been recorded.', route('admin.concerts.edit', $concert))),
            'photos_uploaded' => app(ConcertPhotoUploadStatus::class)->forEvent($event),
            'photo_release' => $this->photoReleaseStatus($event, $concert, $photoAlbums, $groups),
            'final_release' => $this->videoReleased($event, $concert)
                ? $this->status('complete', 'Released', 'The concert video has been approved and released. Families need the studio video access code.', route('admin.concerts.edit', $concert), $concert->published_at)
                : $this->status('not_started', 'Not released', 'Waiting for the studio director to review and release the concert video.', $concert ? route('admin.concerts.edit', $concert) : route('admin.concerts.index')),
            default => $this->status('not_started', 'Not started', 'Open the task to update it.', route('admin.scheduling-events.show', $event)),
        };
    }

    private function videoReleased(SchedulingEvent $event, mixed $concert): bool
    {
        if (! $concert?->isPubliclyAvailable() || ! $concert->published_at) {
            return false;
        }
        $review = ConcertDirectorReview::where('scheduling_event_id', $event->id)->first();

        return ! $review || ($review->released_at && $review->released_batch_id === ConcertVideoBatch::where('scheduling_event_id', $event->id)->latest('id')->value('id'));
    }

    private function correctionsStatus(SchedulingEvent $event): array
    {
        $data = app(ConcertDirectorReviews::class)->page($event);
        if (! $data['review']->revision) {
            return $data['released']
                ? $this->status('not_required', 'No corrections', 'The studio released the video without requesting corrections.', null)
                : $this->status('waiting', 'Pending', 'Awaiting studio review. No corrections have been submitted.', null);
        }
        $complete = $data['checksReady'] && $data['currentMail'];

        return $this->status($complete ? 'complete' : 'in_progress', $complete ? 'Completed' : 'Corrections',
            $complete ? 'Corrections email sent to the studio director.' : 'Review feedback, complete and upload corrections, then send the email.', null,
            $complete ? $data['currentMail']->sent_at : null);
    }

    private function photoReleaseStatus(SchedulingEvent $event, mixed $concert, Collection $albums, Collection $groups): array
    {
        $url = route('admin.concert-tracker.tasks.show', [$event, 'photo_release']);
        if ($albums->isNotEmpty() && $albums->every(fn (MediaCollection $album): bool => $album->published_at !== null && $album->status->value === 'published')) {
            return $this->status('complete', 'Released', 'All photo albums are released.', $url, $groups->max('released_at'));
        }
        if (app(ConcertPhotoUploadStatus::class)->forEvent($event)['key'] === 'complete') {
            return $this->status('in_progress', 'Ready to release', 'All required photographers have finished uploading. Prepare and release the gallery.', $url);
        }

        return $this->status('waiting', 'Pending', 'Waiting for all required photographers to finish uploading.', $url);
    }

    private function studioMediaActivity(Collection $files): string
    {
        $uploaded = $files->sortByDesc('uploaded_at')->first();
        $reviewed = $files->whereNotNull('reviewed_at')->sortByDesc('reviewed_at')->first();
        $activity = 'Uploaded '.$uploaded->uploaded_at?->format('j M Y, g:i a').' · '.($uploaded->uploadedBy?->name ?? 'Former account');
        if ($reviewed) {
            $activity .= ' | Reviewed '.$reviewed->reviewed_at->format('j M Y, g:i a').' · '.($reviewed->reviewedBy?->name ?? 'Former staff member');
        }

        return $activity;
    }

    private function crewStatus(SchedulingEvent $event): array
    {
        $roster = app(EventRosterStatus::class)->forEvent($event);

        return $this->status($event->cancelled_at ? 'not_required' : ($roster['ready'] ? 'complete' : 'in_progress'), $roster['label'],
            $roster['ready'] ? 'Required crew are published and acknowledged.' : 'Review availability, assignments and acknowledgements.',
            route('admin.concert-tracker.tasks.show', [$event, 'crew_ready']), $roster['ready'] ? $event->roster_published_at : null)
            + ['crewSummary' => $roster['crew']];
    }

    private function manualStatus(array $definition, ?ConcertTrackerTask $override): array
    {
        $delivery = $override?->deliveries->first();
        if (in_array($definition['key'], ConcertTrackerTaskCatalog::GUARDED_EMAIL_TASKS, true) && $override?->status === 'complete') {
            $manual = $override->status_override === 'complete';
            $activity = ($manual ? 'Approved ' : 'Sent ').$override->completed_at?->format('j M Y, g:i a').($override->completedBy ? ' · '.$override->completedBy->name : ' · Automation');

            return $this->status('complete', $manual ? 'Approved' : 'Completed', $manual ? 'Email not required; approved manually.' : $definition['label'].' sent.', null, $override->completed_at, $activity);
        }
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
        if ($definition['key'] === 'photo_release') {
            $last = $event->concertBookingItem?->booking?->items->whereNull('cancelled_at')->sortBy(fn ($item) => $item->event_date?->format('Y-m-d').' '.($item->finishes_at ?? '00:00'))->last();
            $date = $last?->event_date ?? $event->event_date;

            return $date ? Carbon::parse($date->format('Y-m-d').' '.($last?->finishes_at ?? '00:00'))->addHours(48) : null;
        }
        if (in_array($definition['key'], ['concert_edited', 'quality_check', 'final_release'], true)) {
            $settings = ConcertTrackerSetting::query()->first();
            $days = $definition['key'] === 'final_release'
                ? ($settings?->approval_target_days ?? 14)
                : ($settings?->editing_target_days ?? 14);

            return $event->event_date?->copy()->addDays($days);
        }
        if ($definition['dueBasis'] === null || $definition['dueOffsetDays'] === null) {
            return null;
        }
        $bookingDates = $event->concertBookingItem?->booking?->items->whereNull('cancelled_at')->pluck('event_date')->filter()->sort();
        $base = match ($definition['dueBasis']) {
            'booking_first' => $bookingDates?->first(),
            'booking_last' => $bookingDates?->last(),
            default => $event->event_date,
        };

        return $base?->copy()->addDays($definition['dueOffsetDays']);
    }

    private function invoiceStatus(?ConcertBooking $booking, ?StudioInvoice $invoice, string $key): array
    {
        return app(InvoiceTrackerWorkflow::class)->status($booking, $invoice);
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
                if ($rows[$start]['pending']) {
                    $start++;

                    continue;
                }
                $scopeKey = $rows[$start]['tasks'][$definition['key']]['scopeKey'];
                $end = $start + 1;
                while ($end < $rows->count() && ! $rows[$end]['pending'] && $rows[$end]['tasks'][$definition['key']]['scopeKey'] === $scopeKey
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
        $tasks = $rows->flatMap(fn (array $row) => $row['tasks']->values())->unique(fn (array $task): string => $task['scopeKey'].':'.$task['definition']['key']);
        $pending = $rows->where('pending', true)->count();

        return [
            'attention' => $pending + $tasks->filter(fn (array $task): bool => $this->needsAttention($task))->count(),
            'near_due' => $tasks->where('nearDue', true)->count(),
            'complete' => $tasks->where('key', 'complete')->count(),
            'open' => $pending + $tasks->reject(fn (array $task): bool => in_array($task['key'], ['complete', 'not_required'], true))->count(),
        ];
    }

    private function needsAttention(array $task): bool
    {
        if (in_array($task['key'], ['complete', 'not_required'], true)) {
            return false;
        }

        return $task['overdue'] || $task['tone'] === 'overdue' || $task['key'] === 'failed'
            || ($task['override']?->follow_up_status !== 'resolved' && $task['override']?->follow_up_date?->lte(today()) && ! in_array($task['key'], ['complete', 'not_required'], true));
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
