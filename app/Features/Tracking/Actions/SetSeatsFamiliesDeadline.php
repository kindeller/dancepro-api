<?php

namespace App\Features\Tracking\Actions;

use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerTask;
use Illuminate\Support\Facades\DB;

class SetSeatsFamiliesDeadline
{
    public function execute(ConcertTrackerEmailDelivery $delivery): void
    {
        $emailTask = $delivery->task;
        if ($emailTask->task_key !== 'final_numbers_email' || $emailTask->scope_type !== 'booking' || ! $delivery->sent_at) {
            return;
        }

        $task = ConcertTrackerTask::firstOrCreate([
            'scope_type' => 'booking', 'scope_id' => $emailTask->scope_id, 'task_key' => 'final_numbers_received',
        ], ['status' => 'not_started']);
        $task = ConcertTrackerTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
        $before = $task->only('due_date_override');
        $task->update(['due_date_override' => $delivery->sent_at->copy()->addDays(10)->toDateString()]);
        DB::table('concert_tracker_task_changes')->insert([
            'concert_tracker_task_id' => $task->id,
            'user_id' => $delivery->created_by_user_id,
            'action' => 'email_deadline_set',
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($task->only('due_date_override') + ['email_delivery_id' => $delivery->id], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
