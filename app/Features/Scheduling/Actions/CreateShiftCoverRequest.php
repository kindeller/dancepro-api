<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use App\Features\Scheduling\Services\EligibleCoverCandidates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateShiftCoverRequest
{
    public function __construct(private readonly EligibleCoverCandidates $eligibleCandidates) {}

    public function execute(SchedulingShiftAssignment $assignment, CrewProfile $requester, array $recipientUuids, ?string $message): ShiftCoverRequest
    {
        $assignment->loadMissing(['crewProfile', 'role', 'shift.schedulingEvent', 'timeEntry']);
        if ($assignment->crew_profile_id !== $requester->id || $assignment->status !== 'published') {
            abort(403);
        }
        if ($assignment->timeEntry?->actual_clock_in_at || $assignment->shift->shift_date->lt(today())) {
            throw ValidationException::withMessages(['recipients' => 'Cover can only be organised before the shift starts.']);
        }
        if ($assignment->coverRequests()->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['recipients' => 'A cover request is already open for this shift.']);
        }

        $eligible = $this->eligibleCandidates->execute($assignment);
        $recipients = $eligible->whereIn('uuid', $recipientUuids)->values();
        if ($recipients->count() !== count(array_unique($recipientUuids))) {
            throw ValidationException::withMessages(['recipients' => 'One or more selected crew members are no longer eligible for this shift.']);
        }

        return DB::transaction(function () use ($assignment, $requester, $recipients, $message): ShiftCoverRequest {
            $coverRequest = ShiftCoverRequest::query()->create([
                'scheduling_shift_assignment_id' => $assignment->id,
                'requested_by_crew_profile_id' => $requester->id,
                'status' => 'open',
                'message' => filled($message) ? trim($message) : null,
            ]);
            foreach ($recipients as $recipient) {
                $coverRequest->recipients()->create(['crew_profile_id' => $recipient->id, 'status' => 'pending']);
                $personalText = $coverRequest->message ? ' Message: '.$coverRequest->message : '';
                CrewNotification::query()->create([
                    'user_id' => $recipient->user_id,
                    'type' => 'cover_request',
                    'title' => 'Cover requested',
                    'message' => "{$requester->preferred_name} is looking for cover for {$assignment->shift->schedulingEvent->name} on {$assignment->shift->shift_date->format('D j M')}.{$personalText}",
                ]);
            }

            return $coverRequest;
        });
    }
}
