<?php

namespace App\Features\Programmes\Services;

use App\Features\Crew\Models\CrewRole;
use App\Features\Programmes\Actions\RegisterPhotoContributions;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class PhotoUploadContext
{
    public function __construct(private readonly RegisterPhotoContributions $register) {}

    public function validate(SchedulingEvent $event, ProgrammePhotoFile $file, User $user): array
    {
        abort_unless(config('programme-media.uploads_enabled'), 503, 'Photo transfer is not enabled on this server.');
        $programme = Programme::where('scheduling_event_id', $event->id)->firstOrFail();
        $contribution = ProgrammePhotoContribution::findOrFail($file->programme_photo_contribution_id);
        $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->findOrFail($contribution->programme_upload_plan_id);
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        abort_if($gallery && $gallery->programme_upload_plan_id !== $plan->id, 409,
            'This event already has uploads for a different working programme. Existing photos have been kept. Use a matching programme before continuing.');
        $role = CrewRole::findOrFail($contribution->crew_role_id);
        $context = $this->register->execute($event, $user, [
            'plan_uuid' => $plan->uuid, 'role_uuid' => $role->uuid,
            'item_uuids' => [$contribution->item_uuid],
        ]);

        return $context + ['plan_id' => $plan->id, 'item_uuid' => $contribution->item_uuid, 'contribution_uuid' => $contribution->uuid];
    }
}
