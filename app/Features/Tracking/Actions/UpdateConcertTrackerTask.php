<?php

namespace App\Features\Tracking\Actions;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateConcertTrackerTask
{
    public function __construct(private readonly ConcertTrackerTaskCatalog $catalog) {}

    public function execute(SchedulingEvent $event, string $taskKey, User $actor, array $input): ConcertTrackerTask
    {
        $definition = $this->catalog->find($taskKey);
        [$scopeType, $scopeId] = $this->scope($event, $definition);

        return DB::transaction(function () use ($scopeType, $scopeId, $taskKey, $actor, $input): ConcertTrackerTask {
            $task = ConcertTrackerTask::firstOrCreate(
                ['scope_type' => $scopeType, 'scope_id' => $scopeId, 'task_key' => $taskKey],
                ['status' => 'not_started'],
            );
            $before = $task->only(['status', 'assignee_user_id', 'due_date_override', 'follow_up_date', 'completed_by_user_id', 'completed_at']);
            $status = $input['status'] ?? $task->status;
            $task->fill([
                'status' => $status,
                'assignee_user_id' => $input['assignee_user_id'] ?? null,
                'due_date_override' => $input['due_date_override'] ?? null,
                'follow_up_date' => $input['follow_up_date'] ?? null,
                'completed_by_user_id' => $status === 'complete' ? ($task->completed_by_user_id ?? $actor->id) : null,
                'completed_at' => $status === 'complete' ? ($task->completed_at ?? now()) : null,
            ])->save();
            if (filled($input['note'] ?? null)) {
                $task->notes()->create([
                    'user_id' => $actor->id,
                    'body' => trim($input['note']),
                    'follow_up_date' => $input['follow_up_date'] ?? null,
                ]);
            }
            DB::table('concert_tracker_task_changes')->insert([
                'concert_tracker_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'updated',
                'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode($task->only(['status', 'assignee_user_id', 'due_date_override', 'follow_up_date', 'completed_by_user_id', 'completed_at']), JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $task->fresh(['assignee', 'completedBy', 'notes.user', 'deliveries']);
        });
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
