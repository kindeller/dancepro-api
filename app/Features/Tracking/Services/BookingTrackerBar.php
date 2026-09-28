<?php

namespace App\Features\Tracking\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\BookingSeatsAndFamilies;
use App\Features\Bookings\Support\ConcertBookingStatus;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BookingTrackerBar
{
    public const STATUSES = [
        'not_started' => 'Not started', 'in_progress' => 'In progress',
        'waiting' => 'Waiting', 'complete' => 'Complete', 'not_required' => 'Not required',
    ];

    public const FOLLOW_UP_STATUSES = ['none' => 'None', 'open' => 'Open', 'waiting' => 'Waiting', 'resolved' => 'Resolved'];

    public function source(ConcertBooking $booking, ?ConcertTrackerTask $task): array
    {
        $approved = $booking->status === ConcertBookingStatus::Approved;
        $key = $approved ? 'complete' : 'in_progress';
        $label = $booking->status === ConcertBookingStatus::Pending ? 'Not approved' : ucfirst($booking->status->value);
        $detail = 'Booking and selected services are held in the unified booking record.';
        if ($task?->status_override !== null) {
            $key = $task->status_override;
            $label = self::STATUSES[$key];
            $detail = 'Manual tracker override. Booking: '.ucfirst($booking->status->value).'.';
        }

        return [
            'key' => $key, 'label' => $label, 'detail' => $detail,
            'sourceUrl' => route('admin.concert-bookings.show', $booking),
            'completedAt' => $task?->status_override !== null ? $task->completed_at : ($approved ? $booking->reviewed_at : null),
            'activity' => null,
        ];
    }

    public function assignees(): Builder
    {
        return User::query()->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('is_admin', true)->orWhereHas('crewProfile'));
    }

    public function emailPage(array $detail, array $returnContext = []): array
    {
        $event = $detail['event'];
        $task = $detail['task'];
        $booking = $event->concertBookingItem?->booking;

        return [
            'definition' => $detail['definition'], 'task' => $task, 'status' => $detail['status'],
            'context' => $detail['task']->scope_type === 'booking' && $booking ? $booking->studio_name.' · Shared across this booking’s concerts' : $event->name.' · '.$event->event_date?->format('j M Y'),
            'automaticLabel' => 'Email delivery', 'emailTask' => true,
            'statusCaption' => $task->status_override === 'complete' ? 'Manually approved' : 'Completed when email is sent',
            'statusTone' => $detail['status']['tone'],
            'dueDate' => $detail['status']['dueDate'], 'overdue' => $detail['status']['overdue'],
            'assignees' => $this->assignees()->orderBy('name')->get(),
            'statuses' => array_diff_key(self::STATUSES, ['complete' => true]),
            'followUpStatuses' => self::FOLLOW_UP_STATUSES,
            'updateUrl' => route('admin.concert-tracker.email-bar.update', [$event, $detail['definition']['key']]),
            'eventUrl' => route('admin.scheduling-events.show', $event),
            'returnUrl' => route('admin.concert-tracker.index', $returnContext ?: ['view' => 'date', 'year' => $event->event_date?->year, 'phase' => $detail['definition']['phase']]),
        ];
    }

    public function sourcePage(array $detail, array $returnContext = []): array
    {
        $event = $detail['event'];

        return [
            'definition' => $detail['definition'], 'task' => $detail['task'], 'status' => $detail['status'],
            'statusTone' => $detail['status']['tone'], 'automaticOnly' => true,
            'automaticHelp' => $detail['definition']['key'] === 'photos_uploaded' ? 'Upload progress comes from the uploader. Each required photo role must finish uploading and confirm Upload Complete; waived work stays separate.' : ($detail['definition']['key'] === 'crew_ready' ? 'This status follows Event availability. Assign, publish and acknowledge the required crew roles there.' : $detail['definition']['description']),
            'statusCaption' => $detail['definition']['key'] === 'photos_uploaded' ? 'Automatic from photo uploads' : ($detail['definition']['key'] === 'crew_ready' ? 'Automatic from event availability' : 'Automatic from source records'),
            'context' => $event->name.' · '.$event->event_date?->format('j M Y'),
            'dueDate' => $detail['status']['dueDate'], 'overdue' => $detail['status']['overdue'],
            'assignees' => $this->assignees()->orderBy('name')->get(), 'statuses' => self::STATUSES,
            'followUpStatuses' => self::FOLLOW_UP_STATUSES,
            'updateUrl' => route('admin.concert-tracker.source-bar.update', [$event, $detail['definition']['key']]),
            'eventUrl' => route('admin.scheduling-events.show', $event),
            'returnUrl' => route('admin.concert-tracker.index', $returnContext ?: ['view' => 'date', 'year' => $event->event_date?->year, 'phase' => $detail['definition']['phase']]),
        ];
    }

    public function releasePage(array $detail, array $returnContext = []): array
    {
        return array_replace($this->sourcePage($detail, $returnContext), [
            'automaticHelp' => $detail['definition']['description'],
            'statusCaption' => match ($detail['definition']['key']) {
                'director_corrections' => 'Automatic after corrections email is sent',
                'photo_release' => 'Automatic from gallery release',
                default => 'Automatic when released by director',
            },
        ]);
    }

    public function invoicePage(array $detail, array $returnContext = []): array
    {
        return array_replace($this->sourcePage($detail, $returnContext), [
            'automaticHelp' => $detail['definition']['description'],
            'statusCaption' => 'Automatic from invoice and payment',
            'context' => $detail['event']->concertBookingItem?->booking?->studio_name.' · Whole booking',
        ]);
    }

    public function videoUploadsPage(array $detail, array $returnContext = []): array
    {
        return array_replace($this->sourcePage($detail, $returnContext), [
            'automaticHelp' => 'Readiness follows the laptop queue, verified files, Finished Uploading, editing, quality checks and studio media inclusions.',
            'statusCaption' => 'Automatic from concert upload readiness',
        ]);
    }

    public function qualityPage(array $detail, array $returnContext = []): array
    {
        return array_replace($this->sourcePage($detail, $returnContext), [
            'automaticHelp' => 'Confirm all 20 checks below to complete the quality check. Unchecking an item reopens it.',
            'statusCaption' => 'Automatic from quality checklist',
        ]);
    }

    public function editingPage(array $detail, array $returnContext = []): array
    {
        return array_replace($this->sourcePage($detail, $returnContext), [
            'automaticOnly' => $detail['status']['sourceDriven'], 'manualTask' => true,
            'automaticHelp' => $detail['status']['detail'],
            'statusCaption' => $detail['status']['sourceDriven'] ? 'Not required for this event' : 'Updated by the editor',
            'manualHelp' => 'Mark editing complete when the concert edit is ready for quality checking. This does not approve or release the video.',
            'assignmentLabel' => 'Editor', 'assignmentCaption' => 'Assigned concert editor',
        ]);
    }

    public function videoTitlesPage(array $detail, array $returnContext = []): array
    {
        return array_replace($this->sourcePage($detail, $returnContext), [
            'automaticOnly' => $detail['status']['sourceDriven'], 'manualTask' => true,
            'automaticHelp' => $detail['status']['sourceDriven'] ? $detail['status']['detail'] : 'Review the title cards below, then use Video titles ready to publish them for the uploader.',
            'statusCaption' => $detail['status']['sourceDriven'] ? 'Not required for this event' : 'Use Video titles ready to publish',
            'statuses' => $detail['status']['key'] === 'complete' ? self::STATUSES : array_diff_key(self::STATUSES, ['complete' => true]),
            'manualHelp' => 'Review the title cards, then use Video titles ready to publish them for the uploader and complete this task. Saving the template alone does not publish files. Reopening this task withdraws the download until it is ready again.',
        ]);
    }

    public function studioMediaPage(array $detail, array $returnContext = []): array
    {
        return array_replace($this->sourcePage($detail, $returnContext), [
            'automaticOnly' => $detail['status']['sourceDriven'],
            'manualTask' => true,
            'automaticHelp' => 'Review the current studio videos and slideshows below. This task completes when every current file is approved.',
            'statusCaption' => $detail['status']['sourceDriven'] ? 'Automatic from studio file reviews' : 'Record receipt or mark not required',
        ]);
    }

    public function numbersPage(ConcertBooking $booking, array $returnContext = []): array
    {
        $service = app(BookingSeatsAndFamilies::class);
        $concerts = $service->concerts($booking);
        $event = $concerts->first(fn ($item) => $item->schedulingEvent)?->schedulingEvent;
        $task = ConcertTrackerTask::query()->with(['assignee', 'notes.user'])->firstOrNew([
            'scope_type' => 'booking', 'scope_id' => $booking->id, 'task_key' => 'final_numbers_received',
        ], ['status' => 'not_started', 'follow_up_status' => 'none']);
        $status = $event ? $service->status($event) : [
            'key' => $concerts->isEmpty() ? 'not_required' : 'waiting',
            'label' => $concerts->isEmpty() ? 'Not required' : ($booking->approximate_family_count ? $booking->approximate_family_count.' families (quoted)' : 'Families to confirm'),
        ];
        $due = $task->due_date_override;
        $open = ! in_array($status['key'], ['complete', 'not_required'], true);
        $tone = $open && $due?->isBefore(today()) ? 'overdue'
            : ($open && $due?->lte(today()->addDays(3)) ? 'near-due' : ($status['key'] === 'waiting' ? 'ready' : $status['key']));

        return [
            'statusTone' => $tone,
            'definition' => app(ConcertTrackerTaskCatalog::class)->find('final_numbers_received'),
            'task' => $task, 'status' => $status, 'automaticOnly' => true,
            'context' => $booking->studio_name.' · Final families and all concert seats',
            'statusCaption' => 'Automatic from confirmed families and seats', 'automaticLabel' => $status['label'],
            'dueDate' => $open ? $due : null, 'overdue' => $open && $due?->isBefore(today()),
            'assignees' => $this->assignees()->orderBy('name')->get(), 'statuses' => self::STATUSES,
            'followUpStatuses' => self::FOLLOW_UP_STATUSES,
            'updateUrl' => route('admin.concert-bookings.numbers-tracker.update', $booking),
            'eventUrl' => $this->bookingEventUrl($booking),
            'returnUrl' => route('admin.concert-tracker.index', $returnContext ?: ['view' => 'date', 'year' => $booking->items->min('event_date')?->year ?? now()->year, 'phase' => 'booking']),
        ];
    }

    public function page(ConcertBooking $booking, array $returnContext = []): array
    {
        $booking->loadMissing('items');
        $task = ConcertTrackerTask::query()->with(['assignee', 'notes.user'])->firstOrNew([
            'scope_type' => 'booking', 'scope_id' => $booking->id, 'task_key' => 'booking_received',
        ], ['status' => 'not_started', 'follow_up_status' => 'none']);
        $status = $this->source($booking, $task);
        $due = $task->due_date_override;
        $confirmation = ConcertTrackerTask::query()->with('deliveries')->where('scope_type', 'booking')->where('scope_id', $booking->id)->where('task_key', 'confirmation_information_email')->first();
        $delivery = $confirmation?->deliveries->sortByDesc('id')->first();
        $confirmationState = $delivery ? ($delivery->is_simulated ? 'Test · ' : '').ucfirst($delivery->status) : ($confirmation?->status === 'complete' ? 'Recorded as sent' : 'Not sent');

        return [
            'eventUrl' => $this->bookingEventUrl($booking),
            'statusTone' => $booking->status === ConcertBookingStatus::Pending ? 'overdue' : $status['key'],
            'confirmationState' => $confirmationState,
            'confirmationUrl' => $booking->status === ConcertBookingStatus::Approved ? route('admin.concert-bookings.confirmation', $booking) : null,
            'definition' => app(ConcertTrackerTaskCatalog::class)->find('booking_received'),
            'task' => $task, 'status' => $status,
            'context' => $booking->studio_name.' · Shared across this booking’s events',
            'automaticLabel' => $this->source($booking, null)['label'],
            'dueDate' => $due,
            'overdue' => $due?->isBefore(today()) && ! in_array($status['key'], ['complete', 'not_required'], true),
            'assignees' => $this->assignees()->orderBy('name')->get(),
            'statuses' => self::STATUSES,
            'followUpStatuses' => self::FOLLOW_UP_STATUSES,
            'updateUrl' => route('admin.concert-bookings.tracker.update', $booking),
            'returnUrl' => route('admin.concert-tracker.index', $returnContext ?: [
                'view' => 'date', 'year' => $booking->items->min('event_date')?->year ?? now()->year, 'phase' => 'booking',
            ]),
        ];
    }

    public function pendingPage(ConcertBooking $booking, string $key, array $context = []): array
    {
        return array_replace($this->page($booking, $context), [
            'definition' => app(ConcertTrackerTaskCatalog::class)->find($key),
            'status' => ['key' => 'waiting', 'label' => 'Pending event', 'detail' => 'Approve and link the applicable event before completing this task.'],
            'statusTone' => 'ready', 'statusCaption' => 'Event not yet available',
            'dueDate' => null, 'overdue' => false, 'confirmationUrl' => null,
            'lockedPanels' => ['status', 'due', 'assignment', 'follow_up'],
        ]);
    }

    private function bookingEventUrl(ConcertBooking $booking): string
    {
        $item = $booking->items->first(fn ($item) => $item->schedulingEvent && ! $item->cancelled_at)
            ?? $booking->items->first();

        return $item?->schedulingEvent
            ? route('admin.scheduling-events.show', $item->schedulingEvent)
            : route('admin.concert-bookings.show', $booking).($item ? '#event-'.$item->uuid : '');
    }
}
