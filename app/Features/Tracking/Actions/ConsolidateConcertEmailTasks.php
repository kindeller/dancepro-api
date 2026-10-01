<?php

namespace App\Features\Tracking\Actions;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertEventGroups;
use Illuminate\Support\Facades\DB;

class ConsolidateConcertEmailTasks
{
    public function __construct(private readonly ConcertEventGroups $groups) {}

    /** Preserve existing delivery history when changing from individual to grouped tasks. */
    public function execute(SchedulingEvent $event, string $taskKey = 'concert_week_email'): void
    {
        if (! in_array($taskKey, ConcertEventGroups::EMAIL_TASKS, true)) {
            throw new \InvalidArgumentException('Only grouped concert emails can be consolidated.');
        }
        $eventIds = $this->groups->items($event)->pluck('scheduling_event_id')->filter()->push($event->id)->unique();
        DB::transaction(function () use ($event, $eventIds, $taskKey): void {
            $existing = ConcertTrackerTask::where('scope_type', 'event')->whereIn('scope_id', $eventIds)
                ->where('task_key', $taskKey)->lockForUpdate()->get();
            if ($existing->isEmpty()) {
                return;
            }
            $anchor = ConcertTrackerTask::firstOrCreate([
                'scope_type' => 'event', 'scope_id' => $this->groups->anchorId($event), 'task_key' => $taskKey,
            ], ['status' => 'not_started']);
            $deliveries = ConcertTrackerEmailDelivery::whereIn('concert_tracker_task_id', $existing->pluck('id'));
            $lastSent = (clone $deliveries)->where('status', 'sent')->orderByDesc('sent_at')->orderByDesc('id')->first();
            if ($lastSent) {
                $anchor->update(['status' => 'complete', 'status_override' => null, 'completed_at' => $lastSent->sent_at, 'completed_by_user_id' => $lastSent->created_by_user_id]);
            } elseif ($complete = $existing->where('status', 'complete')->sortByDesc('completed_at')->first()) {
                $anchor->update($complete->only(['status', 'status_override', 'completed_at', 'completed_by_user_id']));
            }
            $deliveries->update(['concert_tracker_task_id' => $anchor->id]);
            // Old queued bodies and dates were prepared using the individual-event rules.
            $anchor->deliveries()->whereIn('status', ['queued', 'scheduled', 'failed'])->update([
                'status' => 'cancelled', 'cancelled_at' => now(), 'invalidated_at' => now(),
                'cancellation_reason' => 'These events now share a 20-day group and a new deadline. Review the updated group before scheduling.',
            ]);
            $oldIds = $existing->where('id', '!=', $anchor->id)->pluck('id');
            foreach (['concert_tracker_task_notes', 'concert_tracker_task_changes'] as $table) {
                DB::table($table)->whereIn('concert_tracker_task_id', $oldIds)->update(['concert_tracker_task_id' => $anchor->id]);
            }
        });
    }
}
