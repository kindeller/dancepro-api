<?php

namespace App\Features\Tracking\Actions;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertEventGroups;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Models\User;

class UpdateConcertTrackerTask
{
    public function __construct(private readonly ConcertTrackerTaskCatalog $catalog, private readonly SaveConcertTrackerTaskFields $save) {}

    public function execute(SchedulingEvent $event, string $taskKey, User $actor, array $input): ConcertTrackerTask
    {
        $definition = $this->catalog->find($taskKey);
        [$scopeType, $scopeId] = $this->scope($event, $definition);

        return $this->save->execute($scopeType, $scopeId, $taskKey, $actor, $input + [
            'due_date_override' => null,
            'follow_up_date' => null,
        ]);
    }

    public function task(SchedulingEvent $event, array $definition): ConcertTrackerTask
    {
        [$scopeType, $scopeId] = $this->scope($event, $definition);

        return ConcertTrackerTask::firstOrCreate(
            ['scope_type' => $scopeType, 'scope_id' => $scopeId, 'task_key' => $definition['key']],
            ['status' => 'not_started'],
        );
    }

    public function scope(SchedulingEvent $event, array $definition): array
    {
        if (in_array($definition['key'], ConcertEventGroups::EMAIL_TASKS, true)) {
            return ['event', app(ConcertEventGroups::class)->anchorId($event)];
        }
        $event->loadMissing('concertBookingItem.booking');
        if ($definition['scope'] === 'booking' && $event->concertBookingItem?->concert_booking_id) {
            return ['booking', $event->concertBookingItem->concert_booking_id];
        }

        if ($definition['scope'] === 'studio' && $event->studio_id) {
            return ['studio', $event->studio_id];
        }

        return ['event', $event->id];
    }
}
