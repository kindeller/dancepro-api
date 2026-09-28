<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Mail\StudioConversationReply;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerInboundEmail;
use App\Features\Tracking\Models\StudioConversationAttachment;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Models\StudioConversationThread;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class StudioCommunicationHistory
{
    public function __construct(private readonly ConcertTracker $tracker, private readonly StudioHubLoginCodes $loginCodes) {}

    public function inbox(): array
    {
        $studioIds = StudioConversationMessage::query()->pluck('studio_id')
            ->merge(ConcertTrackerEmailDelivery::query()->whereNotNull('scheduling_event_id')->with('event:id,studio_id')->get()->pluck('event.studio_id'))
            ->filter()->unique();
        $studios = Studio::query()->whereIn('id', $studioIds)->with(['contacts', 'concerts'])->orderBy('name')->get();
        $latest = StudioConversationMessage::query()->with('event')->latest('sent_at')->get()->unique('studio_id')->keyBy('studio_id');
        $threads = StudioConversationThread::query()->with('assignedUser')->whereIn('studio_id', $studioIds)->get()->keyBy('studio_id');
        $unread = StudioConversationMessage::query()->where('direction', 'to_dancepro')->whereNull('staff_read_at')
            ->selectRaw('studio_id, count(*) as total')->groupBy('studio_id')->pluck('total', 'studio_id');

        return compact('studios', 'latest', 'unread', 'threads');
    }

    public function forUser(User $user, Studio $studio): array
    {
        abort_unless($this->canView($user, $studio), 403);

        StudioConversationMessage::query()->where('studio_id', $studio->id)
            ->where('direction', 'from_dancepro')->whereNull('studio_read_at')->update(['studio_read_at' => now()]);

        $deliveries = ConcertTrackerEmailDelivery::query()
            ->with('event')
            ->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))
            ->where('status', 'sent')
            ->orderByDesc('sent_at')->orderByDesc('scheduled_at')->orderByDesc('created_at')
            ->get();
        $inbound = ConcertTrackerInboundEmail::query()
            ->with('event')
            ->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))
            ->orderByDesc('received_at')->get();

        $webMessages = StudioConversationMessage::query()->with(['event', 'attachments'])->where('studio_id', $studio->id)->get();
        $communications = $deliveries->map(fn ($delivery): array => [
            'direction' => 'from_dancepro', 'subject' => $delivery->subject, 'body' => $delivery->body,
            'contact' => $delivery->recipient_email, 'event' => $delivery->event?->name,
            'event_uuid' => $delivery->event?->uuid,
            'occurred_at' => $delivery->sent_at ?? $delivery->scheduled_at ?? $delivery->created_at,
            'status' => ($delivery->is_simulated ? 'Test · ' : '').$delivery->status, 'attachments' => collect(),
        ])->concat($inbound->map(fn ($message): array => [
            'direction' => 'to_dancepro', 'subject' => $message->subject, 'body' => $message->body,
            'contact' => $message->sender_email, 'event' => $message->event?->name,
            'event_uuid' => $message->event?->uuid,
            'occurred_at' => $message->received_at, 'status' => 'received', 'attachments' => collect(),
        ]))->concat($webMessages->map(fn ($message): array => [
            'direction' => $message->direction, 'subject' => $message->subject, 'body' => $message->body,
            'contact' => $message->sender_email ?: $message->recipient_email, 'event' => $message->event?->name,
            'event_uuid' => $message->event?->uuid,
            'occurred_at' => $message->sent_at, 'status' => 'sent in DancePro', 'attachments' => $message->attachments,
        ]))->sortByDesc('occurred_at')->values();

        $events = $this->studioEvents($studio);
        $hub = $this->hub($events, $communications, $studio);

        return compact('studio', 'communications', 'events', 'hub');
    }

    public function reply(User $user, Studio $studio, array $data): StudioConversationMessage
    {
        abort_unless($this->canView($user, $studio), 403);
        $event = $this->event($studio, $data['event_uuid'] ?? null);

        $message = StudioConversationMessage::create([
            'studio_id' => $studio->id, 'scheduling_event_id' => $event?->id, 'sender_user_id' => $user->id,
            'direction' => 'to_dancepro', 'channel' => 'web', 'sender_name' => $user->name,
            'sender_email' => $user->email, 'subject' => ($data['subject'] ?? null) ?: 'Message from '.$studio->name,
            'body' => $data['body'], 'sent_at' => now(), 'studio_read_at' => now(),
        ]);
        $this->attach($message, $data['attachment'] ?? null);

        return $message;
    }

    public function adminPage(Studio $studio): array
    {
        StudioConversationMessage::query()->where('studio_id', $studio->id)
            ->where('direction', 'to_dancepro')->whereNull('staff_read_at')->update(['staff_read_at' => now()]);

        $thread = StudioConversationThread::firstOrCreate(['studio_id' => $studio->id]);
        $staff = User::query()->where('is_admin', true)->where('is_active', true)->orderBy('name')->get();

        return $this->forUserData($studio) + ['contactEmails' => $this->contactEmails($studio), 'thread' => $thread->load('assignedUser'), 'staff' => $staff];
    }

    public function adminReply(User $user, Studio $studio, array $data): StudioConversationMessage
    {
        $event = $this->event($studio, $data['event_uuid'] ?? null);
        $recipient = mb_strtolower(trim($data['recipient_email'] ?? ''));
        abort_unless($this->contactEmails($studio)->contains($recipient), 422);
        $message = StudioConversationMessage::create([
            'studio_id' => $studio->id, 'scheduling_event_id' => $event?->id, 'sender_user_id' => $user->id,
            'direction' => 'from_dancepro', 'channel' => 'web_and_email', 'sender_name' => $user->name,
            'sender_email' => $user->email, 'recipient_email' => $recipient,
            'subject' => ($data['subject'] ?? null) ?: 'Message from DancePro', 'body' => $data['body'],
            'sent_at' => now(), 'staff_read_at' => now(),
        ]);
        $this->attach($message, $data['attachment'] ?? null);
        Mail::to($recipient)->send(new StudioConversationReply(
            $message->subject, $message->body, route('customer.studio-communications.index', $studio),
            $user->id,
        ));

        return $message;
    }

    public function assign(Studio $studio, ?int $userId): void
    {
        StudioConversationThread::updateOrCreate(['studio_id' => $studio->id], ['assigned_user_id' => $userId]);
    }

    public function canView(User $user, Studio $studio): bool
    {
        $email = mb_strtolower(trim($user->email));
        $access = StudioAccountAccess::query()->where('studio_id', $studio->id)->where('email', $email)->first();
        if ($access?->status === 'revoked') {
            return false;
        }
        if ($access && $user->hasVerifiedEmail()) {
            if ($access->status !== 'active' || $access->user_id !== $user->id) {
                $access->update(['user_id' => $user->id, 'status' => 'active', 'activated_at' => now(), 'revoked_at' => null]);
            }
            CustomerStudio::updateOrCreate(['user_id' => $user->id, 'studio_id' => $studio->id], ['access_verified_at' => now()]);

            return true;
        }
        if (! $user->canAccessCustomer() || ! CustomerStudio::query()->where('user_id', $user->id)
            ->where('studio_id', $studio->id)->whereNotNull('access_verified_at')->exists()) {
            return false;
        }

        return $this->contactEmails($studio)->contains($email);
    }

    private function contactEmails(Studio $studio): Collection
    {
        $studio->loadMissing(['contacts', 'accountAccesses']);

        return $studio->contacts->flatMap->emailAddresses()
            ->merge($studio->accountAccesses->where('status', 'active')->pluck('email'))
            ->push($studio->contact_email)->filter()
            ->map(fn (string $email): string => mb_strtolower(trim($email)))->unique();
    }

    private function event(Studio $studio, ?string $uuid): mixed
    {
        if (! $uuid) {
            return null;
        }

        return $studio->concerts()->whereHas('bookingItem.schedulingEvent', fn ($events) => $events->where('uuid', $uuid))
            ->with('bookingItem.schedulingEvent')->firstOrFail()->bookingItem->schedulingEvent;
    }

    private function forUserData(Studio $studio): array
    {
        $deliveries = ConcertTrackerEmailDelivery::with('event')->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))->whereIn('status', ['queued', 'scheduled', 'sent'])->get();
        $inbound = ConcertTrackerInboundEmail::with('event')->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))->get();
        $web = StudioConversationMessage::with(['event', 'attachments'])->where('studio_id', $studio->id)->get();
        $communications = $deliveries->map(fn ($m) => ['direction' => 'from_dancepro', 'subject' => $m->subject, 'body' => $m->body, 'contact' => $m->recipient_email, 'event' => $m->event?->name, 'event_uuid' => $m->event?->uuid, 'occurred_at' => $m->sent_at ?? $m->created_at, 'status' => $m->status, 'attachments' => collect()])
            ->concat($inbound->map(fn ($m) => ['direction' => 'to_dancepro', 'subject' => $m->subject, 'body' => $m->body, 'contact' => $m->sender_email, 'event' => $m->event?->name, 'event_uuid' => $m->event?->uuid, 'occurred_at' => $m->received_at, 'status' => 'received', 'attachments' => collect()]))
            ->concat($web->map(fn ($m) => ['direction' => $m->direction, 'subject' => $m->subject, 'body' => $m->body, 'contact' => $m->sender_email ?: $m->recipient_email, 'event' => $m->event?->name, 'event_uuid' => $m->event?->uuid, 'occurred_at' => $m->sent_at, 'status' => 'sent in DancePro', 'attachments' => $m->attachments]))
            ->sortByDesc('occurred_at')->values();
        $events = $this->studioEvents($studio);

        return compact('studio', 'communications', 'events');
    }

    private function attach(StudioConversationMessage $message, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }
        $key = $file->store('studio-communications/'.$message->studio_id.'/'.$message->uuid, 'local');
        StudioConversationAttachment::create([
            'studio_conversation_message_id' => $message->id, 'disk' => 'local', 'storage_key' => $key,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
        ]);
    }

    private function studioEvents(Studio $studio): Collection
    {
        return SchedulingEvent::query()->where('studio_id', $studio->id)
            ->with([
                'concertBookingItem.booking', 'concertBookingItem.concert.codeGroup', 'venue', 'shifts.assignments.crewProfile.user', 'shifts.assignments.role',
                'roleRequirements.crewRole', 'roleSeats', 'studioFiles.uploadedBy', 'studioEventAcknowledgements',
                'studioBookingChangeRequests.requestedBy',
            ])
            ->orderByDesc('event_date')->get();
    }

    private function hub(Collection $events, Collection $communications, Studio $studio): array
    {
        $progress = $this->tracker->statusesForEvents($events);
        $photoLogin = $this->loginCodes->photo($studio);
        $eventCards = $events->map(function (SchedulingEvent $event) use ($progress, $studio, $photoLogin): array {
            $sourceTasks = $progress->get($event->id);
            $sourceState = fn (string $key): string => match ($sourceTasks->get($key)['key'] ?? 'not_started') {
                'complete', 'not_required' => 'complete',
                'in_progress' => 'in_progress',
                default => 'waiting',
            };
            $bookingItem = $event->concertBookingItem;
            $booking = $bookingItem?->booking;
            $concert = $bookingItem?->concert;
            $files = $event->studioFiles->where('is_current', true);
            $programme = $files->firstWhere('kind', 'programme');
            $videos = $files->where('kind', 'studio_video');
            $concertDate = $event->event_date;
            $isConcert = $bookingItem?->item_type?->value === 'concert';
            $seatedRoles = $event->roleRequirements->filter(fn ($requirement): bool => str_contains($requirement->crewRole->code, 'concert-photographer')
                || str_contains($requirement->crewRole->code, 'videographer'));
            if ($isConcert) {
                $expectedCodes = collect([
                    $booking?->wants_concert_photography ? 'concert-photographer-p1' : null,
                    $booking?->wants_concert_videography ? 'concert-videographer' : null,
                ])->filter();
                $assignedRoleIds = $event->shifts->flatMap->assignments->pluck('crew_role_id')->unique();
                $roles = CrewRole::query()->where('is_active', true)
                    ->where(fn ($query) => $query->whereIn('code', $expectedCodes)->orWhereIn('id', $assignedRoleIds))
                    ->get()->filter(fn (CrewRole $role): bool => str_contains($role->code, 'concert-photographer') || str_contains($role->code, 'videographer'));
                foreach ($roles as $role) {
                    if (! $seatedRoles->contains(fn ($requirement): bool => $requirement->crew_role_id === $role->id)) {
                        $seatedRoles->push((object) ['crew_role_id' => $role->id, 'crewRole' => $role]);
                    }
                }
            }
            $savedSeats = $event->roleSeats->keyBy('crew_role_id');
            $seatingComplete = $seatedRoles->isEmpty() || $seatedRoles->every(function ($requirement) use ($savedSeats): bool {
                $minimum = str_contains($requirement->crewRole->code, 'videographer') ? 5 : 1;

                return count($savedSeats->get($requirement->crew_role_id)?->seats ?? []) >= $minimum;
            });
            $videoRequired = $isConcert && (bool) $booking?->wants_concert_videography;
            $portraitRequired = ! $isConcert && (bool) $booking?->wants_portrait_photography;
            $portraitAcknowledgements = $event->studioEventAcknowledgements->pluck('requirement');
            $portraitRequirementsComplete = ! $portraitRequired || collect(['portrait_space', 'portrait_dancers'])
                ->every(fn (string $requirement): bool => $portraitAcknowledgements->contains($requirement));
            $crew = $event->shifts->flatMap->assignments
                ->filter(fn ($assignment): bool => in_array($assignment->status, ['assigned', 'published'], true))
                ->map(fn ($assignment): array => [
                    'name' => $assignment->crewProfile?->preferred_name ?: $assignment->crewProfile?->user?->name,
                    'role' => $assignment->role?->name,
                    'published' => $assignment->status === 'published',
                ])->filter(fn (array $person): bool => filled($person['name']))
                ->unique(fn (array $person): string => $person['name'].'|'.$person['role'])->values();
            $production = collect([
                [
                    'label' => 'Booking confirmed',
                    'status' => $bookingItem?->approval_status === 'approved' ? 'complete' : 'waiting',
                    'detail' => 'DancePro',
                    'url' => null,
                ],
                [
                    'label' => 'Crew assigned',
                    'status' => $sourceState('crew_ready'),
                    'detail' => $crew->isNotEmpty() && $crew->every('published')
                        ? $crew->pluck('name')->map(fn (string $name): string => str($name)->before(' ')->toString())->unique()->join(', ')
                        : 'DancePro',
                    'url' => null,
                ],
                (($isConcert && $booking?->wants_concert_photography) || $portraitRequired) ? [
                    'label' => 'Photo gallery released',
                    'loginCode' => $photoLogin,
                    'status' => $sourceState('photo_release'),
                    'detail' => 'DancePro',
                    'url' => $concert && $sourceState('photo_release') === 'complete'
                        ? route('customer.concerts.photos.show', $concert) : null,
                ] : null,
                $videoRequired ? [
                    'label' => 'Review & release concert video',
                    'loginCode' => $concert?->approved_at ? $this->loginCodes->video($concert, $studio) : null,
                    'status' => $sourceState('final_release'),
                    'detail' => 'DancePro',
                    'url' => auth()->user() && app(ConcertDirectorReviews::class)->canReview(auth()->user(), $event)
                        ? route('customer.concert-review.show', $event) : null,
                ] : null,
            ])->filter()->values();
            $checklist = collect([
                $this->hubTask('booking', 'Check booking details', $bookingItem?->approval_status === 'approved', null,
                    'Review the date, venue, times and booked services. Send a change request if anything is incorrect.'),
                $portraitRequired ? $this->hubTask('portrait_requirements', 'Confirm portrait shoot requirements', $portraitRequirementsComplete, $concertDate?->copy()->subDay(),
                    'Confirm the portrait area is ready and every dancer will be brought for their photo.') : null,
                $this->hubTask('seating', 'Enter reserved seats', $seatingComplete, $seatedRoles->isNotEmpty() ? $concertDate?->copy()->subDays(7) : null,
                    $seatedRoles->isEmpty() ? 'No auditorium seats are required for this event.' : 'Enter the row and seat for every photography and videography position.'),
                $this->hubTask('programme', 'Upload programme', ! $isConcert || $programme !== null, $isConcert ? $concertDate?->copy()->subDays(7) : null,
                    $isConcert ? 'Upload the programme for this event. PDF is preferred, but other file types are accepted.' : 'A programme is not required for this event.'),
                $this->hubTask('video', 'Upload videos and slideshows', ! $videoRequired || $videos->isNotEmpty(), $videoRequired ? $concertDate?->copy()->addDays(2) : null,
                    $videoRequired ? 'Upload every opening video, slideshow or supplied clip for this concert.' : 'No studio video files are required for the booked services.'),
            ])->filter()->values();

            return [
                'event' => $event,
                'bookingItem' => $bookingItem,
                'booking' => $booking,
                'title' => $bookingItem?->title ?: $event->name,
                'isConcert' => $isConcert,
                'services' => collect([
                    $isConcert && $booking?->wants_concert_photography ? 'Photography' : null,
                    $isConcert && $booking?->wants_concert_videography ? 'Videography' : null,
                    ! $isConcert && $booking?->wants_portrait_photography ? 'Dress rehearsal portraits' : null,
                ])->filter()->unique()->values(),
                'seatedRoles' => $seatedRoles,
                'savedSeats' => $savedSeats,
                'programme' => $programme,
                'videos' => $videos,
                'portraitAcknowledgements' => $portraitAcknowledgements,
                'files' => $files,
                'crew' => $crew,
                'production' => $production,
                'changeRequests' => $event->studioBookingChangeRequests,
                'checklist' => $checklist,
                'completeCount' => $checklist->where('complete', true)->count(),
                'totalCount' => $checklist->count(),
                'nextTask' => $checklist->firstWhere('complete', false),
                'arrival' => $event->shifts->sortBy('posted_arrival_at')->first()?->posted_arrival_at,
            ];
        });

        return [
            'events' => $eventCards,
            'upcomingCount' => $eventCards->filter(fn (array $card): bool => $card['event']->event_date?->copy()->endOfDay()->isFuture() ?? false)->count(),
            'actionCount' => $eventCards->sum(fn (array $card): int => $card['totalCount'] - $card['completeCount']),
            'messageCount' => $communications->count(),
        ];
    }

    private function hubTask(string $key, string $label, bool $complete, ?CarbonInterface $dueDate, string $instruction): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'complete' => $complete,
            'dueDate' => $complete ? null : $dueDate,
            'overdue' => ! $complete && $dueDate?->copy()->endOfDay()->isPast(),
            'instruction' => $instruction,
        ];
    }
}
