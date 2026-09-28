<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Services\ConcertTracker;

class CurrentBookingTimeline
{
    public function __construct(private readonly ConcertBookingSummary $summary, private readonly ConcertTracker $tracker) {}

    public function forBooking(ConcertBooking $booking, Studio $studio): array
    {
        $booking->load('items.schedulingEvent', 'items.concert');
        $active = clone $booking;
        $active->setRelation('items', $booking->items->whereNull('cancelled_at')->values());
        $details = $booking->only(['studio_name', 'contact_name', 'contact_email', 'contact_phone', 'wants_portrait_photography', 'wants_concert_photography', 'wants_concert_videography', 'approximate_family_count', 'final_family_count', 'family_count_confirmed_at', 'postal_address', 'previous_video_feedback', 'accepted_requirements']);
        $details['accepted_requirements'] ??= [];
        $receipt = $this->summary->snapshot($active, $details);
        $events = $active->items->pluck('schedulingEvent')->filter()->values();
        $states = $this->tracker->statusesForEvents($events);
        $progress = $active->items->map(function ($item) use ($states, $studio): array {
            $tasks = $states->get($item->scheduling_event_id);
            $rows = collect(['booking_received', 'crew_ready', 'programme_prepared', 'photos_uploaded', 'photo_release', 'concert_edited', 'quality_check', 'video_uploaded', 'final_release', 'invoice_sent'])
                ->map(function (string $key) use ($tasks, $item, $studio): ?array {
                    $task = $tasks?->get($key);
                    if (! $task || $task['key'] === 'not_required') {
                        return null;
                    }
                    $url = route('customer.studio-communications.index', [$studio, 'tab' => 'events']).'#events';
                    if ($key === 'invoice_sent') {
                        $url = route('customer.studio-invoices.index', $studio);
                    }
                    if ($key === 'photo_release' && $task['key'] === 'complete' && $item->concert) {
                        $url = route('customer.concerts.photos.show', $item->concert);
                    }
                    if ($key === 'final_release' && $task['key'] === 'complete' && $item->concert) {
                        $url = route('concerts.show', $item->concert);
                    }

                    return [
                        'title' => $task['definition']['label'],
                        'status' => $task['label'], 'complete' => $task['key'] === 'complete',
                        'due' => $task['dueDate'], 'completed' => $task['completedAt'],
                        'overdue' => $task['overdue'], 'url' => $url,
                    ];
                })->filter()->values();

            return ['title' => $item->title ?: 'Event '.$item->event_date->format('j M Y'), 'tasks' => $rows];
        });

        return $receipt + [
            'live' => true, 'status' => $booking->status->value,
            'updated_at' => $booking->updated_at,
            'cancelled_events' => $booking->items->whereNotNull('cancelled_at')->pluck('title'),
            'progress' => $progress,
        ];
    }
}
