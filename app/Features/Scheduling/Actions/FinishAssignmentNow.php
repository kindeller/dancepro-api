<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FinishAssignmentNow
{
    public function __construct(private readonly SaveAssignmentTime $saveTime) {}

    public function execute(SchedulingShiftAssignment $assignment, User $user): AssignmentTimeEntry
    {
        $assignment->loadMissing('shift');
        $entry = $assignment->timeEntry;
        if ($entry?->actual_finish_at) {
            throw ValidationException::withMessages(['time' => 'This shift already has a finish time.']);
        }
        if (! $entry?->actual_clock_in_at && (! $assignment->shift->posted_arrival_at || now()->lt($assignment->shift->posted_arrival_at->copy()->addHours(2)))) {
            throw ValidationException::withMessages(['time' => 'Clock out becomes available two hours after the posted arrival time.']);
        }

        return $this->saveTime->execute(
            $assignment,
            $user,
            $entry?->actual_clock_in_at?->toDateTimeString(),
            now()->toDateTimeString(),
            $entry?->optional_note,
            'crew',
        );
    }
}
