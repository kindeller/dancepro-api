<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClockInAssignment
{
    public function __construct(private SaveAssignmentTime $saveTime) {}

    public function execute(SchedulingShiftAssignment $assignment, User $user): AssignmentTimeEntry
    {
        if ($assignment->timeEntry?->actual_clock_in_at) {
            throw ValidationException::withMessages(['time' => 'You are already clocked in for this shift.']);
        }

        return $this->saveTime->execute($assignment, $user, now()->toDateTimeString(), null, null, 'crew');
    }
}
