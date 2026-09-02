<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Support\UserType;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use App\Features\Scheduling\Services\EligibleCoverCandidates;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptShiftCoverRequest
{
    public function __construct(private readonly EligibleCoverCandidates $eligibleCandidates) {}

    public function execute(ShiftCoverRequest $coverRequest, CrewProfile $replacement): SchedulingShiftAssignment
    {
        return DB::transaction(function () use ($coverRequest, $replacement): SchedulingShiftAssignment {
            $lockedRequest = ShiftCoverRequest::query()->lockForUpdate()->findOrFail($coverRequest->id);
            $recipient = $lockedRequest->recipients()->where('crew_profile_id', $replacement->id)->lockForUpdate()->first();
            if ($lockedRequest->status !== 'open' || ! $recipient || $recipient->status !== 'pending') {
                throw ValidationException::withMessages(['cover' => 'This cover request is no longer available.']);
            }

            $assignment = SchedulingShiftAssignment::query()->lockForUpdate()->findOrFail($lockedRequest->scheduling_shift_assignment_id);
            if (! $this->eligibleCandidates->contains($assignment, $replacement)) {
                throw ValidationException::withMessages(['cover' => 'You are no longer eligible or are now scheduled elsewhere at this time.']);
            }

            $originalCrew = $assignment->crewProfile;
            $assignment->update([
                'crew_profile_id' => $replacement->id,
                'acknowledgement_status' => 'not_acknowledged',
                'acknowledged_at' => null,
                'notified_at' => now(),
            ]);
            $assignment->checklistCompletions()->delete();
            $assignment->shift->schedulingEvent->update(['roster_status' => 'changed']);
            $assignment->shift->availabilityResponses()->where('crew_profile_id', $replacement->id)->update(['locked_at' => now()]);

            $lockedRequest->update(['status' => 'accepted', 'accepted_by_crew_profile_id' => $replacement->id, 'accepted_at' => now()]);
            $recipient->update(['status' => 'accepted', 'responded_at' => now()]);
            $otherRecipients = $lockedRequest->recipients()->whereKeyNot($recipient->id)->with('crewProfile')->get();
            $lockedRequest->recipients()->whereKeyNot($recipient->id)->update(['status' => 'closed', 'responded_at' => now()]);

            $this->notify($replacement->user_id, 'Cover confirmed', "You are now assigned to {$assignment->shift->schedulingEvent->name} on {$assignment->shift->shift_date->format('D j M')}. Please acknowledge the shift.");
            $this->notify($originalCrew->user_id, 'Cover confirmed', "{$replacement->preferred_name} is now covering your {$assignment->shift->schedulingEvent->name} shift on {$assignment->shift->shift_date->format('D j M')}.");
            foreach ($otherRecipients as $other) {
                $this->notify($other->crewProfile->user_id, 'Cover request filled', "The {$assignment->shift->schedulingEvent->name} cover request has been filled by another crew member.");
            }
            User::query()->where('is_active', true)->whereIn('type', [UserType::Staff->value, UserType::Admin->value])->each(function (User $user) use ($assignment, $originalCrew, $replacement): void {
                $this->notify($user->id, 'Crew cover completed', "{$replacement->preferred_name} replaced {$originalCrew->preferred_name} for {$assignment->shift->schedulingEvent->name} on {$assignment->shift->shift_date->format('D j M')}.");
            });

            return $assignment->fresh();
        });
    }

    private function notify(int $userId, string $title, string $message): void
    {
        CrewNotification::query()->create(['user_id' => $userId, 'type' => 'cover_request', 'title' => $title, 'message' => $message]);
    }
}
