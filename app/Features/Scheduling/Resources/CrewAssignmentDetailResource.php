<?php

namespace App\Features\Scheduling\Resources;

use Illuminate\Http\Request;

class CrewAssignmentDetailResource extends CrewAssignmentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $event = $this->shift->schedulingEvent;
        $venue = $event->venue;

        return [...parent::toArray($request),
            'venue' => $venue ? [
                'id' => $venue->uuid,
                'name' => $venue->name,
                'address' => collect([
                    $venue->address_line_1, $venue->address_line_2, $venue->suburb,
                    $venue->state, $venue->postcode,
                ])->filter()->implode(', '),
            ] : null,
            'crew_brief' => $event->crew_brief,
            'team_leader_notes' => $this->is_team_leader ? $event->team_leader_notes : null,
            'team' => $this->shift->assignments->where('status', 'published')->map(fn ($assignment): array => [
                'id' => $assignment->crewProfile->uuid,
                'name' => $assignment->crewProfile->preferred_name ?: $assignment->crewProfile->user->name,
                'phone' => $assignment->crewProfile->phone,
                'profile_photo_url' => null,
            ])->values(),
            'checklist' => [],
            'documents' => [],
        ];
    }
}
