<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinishTeamAssignments
{
    public function __construct(private SaveAssignmentTime $saveTime) {}

    public function execute(Collection $assignments, User $teamLeader, string $finish, ?string $note): int
    {
        $finished = 0;
        foreach ($assignments as $assignment) {
            if ($assignment->timeEntry?->actual_finish_at) {
                continue;
            }
            $clockIn = $assignment->timeEntry?->actual_clock_in_at?->toDateTimeString();
            $this->saveTime->execute($assignment, $teamLeader, $clockIn, $finish, $note, 'team_leader');
            CrewNotification::query()->create([
                'user_id' => $assignment->crewProfile->user_id,
                'type' => 'shift_finished_by_team_leader',
                'title' => 'Shift finished by Team Leader',
                'message' => 'Your finish time was recorded as '.Carbon::parse($finish)->format('g:i a').'. You can correct it if needed.',
            ]);
            $finished++;
        }

        return $finished;
    }
}
