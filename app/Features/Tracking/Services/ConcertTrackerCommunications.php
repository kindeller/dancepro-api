<?php

namespace App\Features\Tracking\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerInboundEmail;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;

class ConcertTrackerCommunications
{
    public function __construct(private readonly ConcertTrackerTaskCatalog $catalog) {}

    public function eventPage(SchedulingEvent $event): array
    {
        abort_unless($event->event_type->value === 'concert', 404);
        $event->loadMissing('studio', 'concertBookingItem.booking');
        $bookingId = $event->concertBookingItem?->concert_booking_id;
        $deliveries = ConcertTrackerEmailDelivery::query()
            ->with(['template', 'createdBy', 'cancelledBy'])
            ->where(function ($query) use ($event, $bookingId): void {
                $query->where('scheduling_event_id', $event->id)
                    ->orWhere(function ($legacy) use ($event, $bookingId): void {
                        $legacy->whereNull('scheduling_event_id')->whereHas('task', function ($task) use ($event, $bookingId): void {
                            $task->where(fn ($scope) => $scope->where('scope_type', 'event')->where('scope_id', $event->id));
                            if ($bookingId) {
                                $task->orWhere(fn ($scope) => $scope->where('scope_type', 'booking')->where('scope_id', $bookingId));
                            }
                        });
                    });
            })->latest()->get();
        $inbound = ConcertTrackerInboundEmail::query()->where('scheduling_event_id', $event->id)->latest('received_at')->get();
        $summary = [
            'sent' => $deliveries->where('status', 'sent')->count(),
            'opened' => $deliveries->whereNotNull('first_opened_at')->count(),
            'received' => $inbound->count(),
            'failed' => $deliveries->where('status', 'failed')->count(),
        ];

        return compact('event', 'deliveries', 'inbound', 'summary');
    }

    public function templatesPage(): array
    {
        $definitions = $this->catalog->all()->keyBy('templateKey');
        $templates = ConcertTrackerEmailTemplate::query()->orderBy('id')->get();

        return compact('templates', 'definitions');
    }
}
