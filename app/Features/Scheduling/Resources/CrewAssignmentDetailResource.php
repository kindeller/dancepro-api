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
                'map_url' => $venue->map_path ? route('api.v1.assignments.files.download', ['assignment' => $this->uuid, 'kind' => 'venue-map']) : null,
                'reference_image_url' => $venue->reference_image_path ? route('api.v1.assignments.files.download', ['assignment' => $this->uuid, 'kind' => 'venue-reference']) : null,
                'parking_notes' => $venue->parking_notes,
                'access_notes' => $venue->access_notes,
                'operational_notes' => $venue->operational_notes,
            ] : null,
            'programme_url' => $event->programme_path ? route('api.v1.assignments.files.download', ['assignment' => $this->uuid, 'kind' => 'programme']) : null,
            'crew_brief' => $event->crew_brief,
            'team_leader_notes' => $this->is_team_leader ? $event->team_leader_notes : null,
            'team' => $this->shift->assignments->where('status', 'published')->map(fn ($assignment): array => [
                'id' => $assignment->crewProfile->uuid,
                'name' => $assignment->crewProfile->preferred_name ?: $assignment->crewProfile->user->name,
                'phone' => $assignment->crewProfile->phone,
                'profile_photo_url' => null,
            ])->values(),
            'checklist' => $this->resource->relationLoaded('mobileChecklist')
                ? $this->resource->getRelation('mobileChecklist')
                : [],
            'documents' => $this->resource->relationLoaded('mobileDocuments')
                ? $this->resource->getRelation('mobileDocuments')
                : [],
        ];
    }
}
