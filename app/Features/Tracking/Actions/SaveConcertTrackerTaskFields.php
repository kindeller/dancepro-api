<?php

namespace App\Features\Tracking\Actions;

use App\Features\Programmes\Services\ConcertTitlePackages;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Services\StudioInvoices;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertQualityChecklist;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveConcertTrackerTaskFields
{
    private const FIELDS = ['status', 'status_override', 'assignee_user_id', 'due_date_override', 'follow_up_date', 'follow_up_status', 'completed_by_user_id', 'completed_at'];

    public function execute(string $scopeType, int $scopeId, string $taskKey, User $actor, array $input): ConcertTrackerTask
    {
        if (in_array($taskKey, ['director_corrections', 'invoice_sent'], true) && (array_key_exists('status', $input) || array_key_exists('status_override', $input))) {
            throw ValidationException::withMessages(['status' => 'Complete this task through its source workflow.']);
        }
        if ($taskKey === 'video_titles' && ($input['status_override'] ?? $input['status'] ?? null) === 'complete') {
            $event = SchedulingEvent::findOrFail($scopeId);
            if (! app(ConcertTitlePackages::class)->current($event)) {
                throw ValidationException::withMessages(['status_override' => 'Use Video titles ready to publish the title files first.']);
            }
        }

        return DB::transaction(function () use ($scopeType, $scopeId, $taskKey, $actor, $input): ConcertTrackerTask {
            $task = ConcertTrackerTask::firstOrCreate(
                ['scope_type' => $scopeType, 'scope_id' => $scopeId, 'task_key' => $taskKey],
                ['status' => 'not_started'],
            );
            $task = ConcertTrackerTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($taskKey === 'quality_check' && ($input['status_override'] ?? $input['status'] ?? null) === 'complete'
                && ! app(ConcertQualityChecklist::class)->summary($task)['complete']) {
                throw ValidationException::withMessages(['status_override' => 'Complete all 20 quality checks first.']);
            }
            $before = $task->only(self::FIELDS);
            $fields = Arr::only($input, ['status', 'status_override', 'assignee_user_id', 'due_date_override', 'follow_up_date', 'follow_up_status']);
            if (array_key_exists('status_override', $fields) || array_key_exists('status', $fields)) {
                $status = array_key_exists('status_override', $fields) ? $fields['status_override'] : $fields['status'];
                $fields['completed_by_user_id'] = $status === 'complete' ? ($task->completed_by_user_id ?? $actor->id) : null;
                $fields['completed_at'] = $status === 'complete' ? ($task->completed_at ?? now()) : null;
            }
            $task->fill($fields)->save();
            if ($taskKey === 'invoice_sent' && array_key_exists('due_date_override', $fields)) {
                $invoice = StudioInvoice::where('active_booking_id', $scopeId)->lockForUpdate()->first();
                if ($invoice?->sent_at) {
                    $due = $fields['due_date_override'] ?? $invoice->sent_at->copy()->addDays(14)->toDateString();
                    $invoice->update(['due_date' => $due]);
                    app(StudioInvoices::class)->audit($invoice, $actor, 'due_date_updated', ['due_date' => $due]);
                }
            }
            if (filled($input['note'] ?? null)) {
                $task->notes()->create([
                    'user_id' => $actor->id,
                    'body' => trim($input['note']),
                    'follow_up_date' => $task->follow_up_date,
                ]);
            }
            DB::table('concert_tracker_task_changes')->insert([
                'concert_tracker_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'updated',
                'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode($task->only(self::FIELDS), JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $task->fresh(['assignee', 'completedBy', 'notes.user', 'deliveries']);
        });
    }
}
