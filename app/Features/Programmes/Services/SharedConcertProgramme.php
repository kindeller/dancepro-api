<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;

class SharedConcertProgramme
{
    public function current(Programme $programme): ?ProgrammeUploadPlan
    {
        $revision = $programme->revisions()->orderByDesc('version')->first();
        if (! $revision || $revision->snapshot['event_type'] !== 'concert') {
            return null;
        }
        $plans = ProgrammeUploadPlan::where('programme_id', $programme->id)->where('programme_revision_id', $revision->id);
        // Existing galleries retain their plan when upgrading older installations.
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $programme->scheduling_event_id)->first();
        if ($gallery) {
            return (clone $plans)->find($gallery->programme_upload_plan_id);
        }
        if ($programme->shared_upload_plan_id) {
            return (clone $plans)->find($programme->shared_upload_plan_id);
        }

        return $plans->orderBy('id')->first();
    }

    public function forEvent(SchedulingEvent $event): array
    {
        return DB::transaction(function () use ($event): array {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $programme = Programme::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $revision = $programme->revisions()->orderByDesc('version')->firstOrFail();
            $plan = $this->current($programme);

            return ['event_uuid' => $event->uuid, 'revision_uuid' => $revision->uuid,
                'plan' => $plan ? ['uuid' => $plan->uuid, 'event_uuid' => $event->uuid,
                    'revision_uuid' => $plan->snapshot['revision_uuid'], 'digest' => $plan->digest] : null,
                'programme' => $plan?->snapshot];
        });
    }
}
