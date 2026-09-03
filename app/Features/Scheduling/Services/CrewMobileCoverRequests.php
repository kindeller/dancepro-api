<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use Illuminate\Support\Collection;

class CrewMobileCoverRequests
{
    /** @return array{received: Collection<int, ShiftCoverRequest>, sent: Collection<int, ShiftCoverRequest>} */
    public function for(CrewProfile $profile): array
    {
        return [
            'received' => ShiftCoverRequest::query()->whereHas('recipients', fn ($query) => $query->where('crew_profile_id', $profile->id))->with($this->relations())->latest()->get(),
            'sent' => ShiftCoverRequest::query()->where('requested_by_crew_profile_id', $profile->id)->with($this->relations())->latest()->get(),
        ];
    }

    public function findVisible(CrewProfile $profile, ShiftCoverRequest $coverRequest): ShiftCoverRequest
    {
        return ShiftCoverRequest::query()->whereKey($coverRequest->id)
            ->where(function ($query) use ($profile): void {
                $query->where('requested_by_crew_profile_id', $profile->id)
                    ->orWhereHas('recipients', fn ($recipients) => $recipients->where('crew_profile_id', $profile->id));
            })->with($this->relations())->firstOrFail();
    }

    public function findReceived(CrewProfile $profile, ShiftCoverRequest $coverRequest): ShiftCoverRequest
    {
        return ShiftCoverRequest::query()->whereKey($coverRequest->id)
            ->whereHas('recipients', fn ($query) => $query->where('crew_profile_id', $profile->id))->firstOrFail();
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['requester.user', 'acceptedBy.user', 'recipients.crewProfile.user', 'assignment.role', 'assignment.shift.schedulingEvent'];
    }
}
