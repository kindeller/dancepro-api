<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaveAssignmentTime
{
    public function execute(SchedulingShiftAssignment $assignment, User $changedBy, ?string $clockIn, ?string $finish, ?string $note, string $source): AssignmentTimeEntry
    {
        $assignment->loadMissing('shift.schedulingEvent');

        return DB::transaction(function () use ($assignment, $changedBy, $clockIn, $finish, $note, $source): AssignmentTimeEntry {
            $entry = $assignment->timeEntry()->firstOrCreate();
            abort_if($entry->locked_at !== null, 422, 'This approved timesheet is locked. Return it before making a correction.');
            $newClockIn = $this->parseAssignmentTime($assignment, $clockIn);
            $newFinish = $this->parseAssignmentTime($assignment, $finish);
            $clockInChanged = $entry->actual_clock_in_at?->timestamp !== $newClockIn?->timestamp;
            $finishChanged = $entry->actual_finish_at?->timestamp !== $newFinish?->timestamp;
            $this->auditChange($entry, $changedBy, 'actual_clock_in_at', $entry->actual_clock_in_at, $newClockIn, $note);
            $this->auditChange($entry, $changedBy, 'actual_finish_at', $entry->actual_finish_at, $newFinish, $note);

            $payableStart = null;
            if ($assignment->shift->schedulingEvent->event_type === SchedulingEventType::Competition) {
                $payableStart = $newClockIn;
                if ($newClockIn && $assignment->shift->posted_arrival_at && $assignment->shift->posted_arrival_at->gt($newClockIn)) {
                    $payableStart = $assignment->shift->posted_arrival_at;
                }
            }
            $entry->fill([
                'actual_clock_in_at' => $newClockIn,
                'clock_in_recorded_at' => $newClockIn ? ($clockInChanged ? now() : $entry->clock_in_recorded_at) : null,
                'clock_in_source' => $newClockIn ? ($clockInChanged ? $source : $entry->clock_in_source) : null,
                'payable_start_at' => $payableStart,
                'actual_finish_at' => $newFinish,
                'finish_recorded_at' => $newFinish ? ($finishChanged ? now() : $entry->finish_recorded_at) : null,
                'finish_source' => $newFinish ? ($finishChanged ? $source : $entry->finish_source) : null,
                'optional_note' => filled($note) ? trim($note) : null,
            ])->save();

            return $entry->refresh();
        });
    }

    private function parseAssignmentTime(SchedulingShiftAssignment $assignment, ?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return Carbon::parse($assignment->shift->shift_date->toDateString().' '.$value);
        }

        return Carbon::parse($value);
    }

    private function auditChange(AssignmentTimeEntry $entry, User $changedBy, string $field, mixed $oldValue, mixed $newValue, ?string $note): void
    {
        if ($oldValue === null || $oldValue?->timestamp === $newValue?->timestamp) {
            return;
        }
        $entry->audits()->create([
            'changed_by_user_id' => $changedBy->id,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'optional_note' => filled($note) ? trim($note) : null,
        ]);
    }
}
