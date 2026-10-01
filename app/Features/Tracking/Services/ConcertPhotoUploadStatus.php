<?php

namespace App\Features\Tracking\Services;

use App\Features\Crew\Models\CrewRole;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\PhotoUploadProgress;
use App\Features\Programmes\Services\ProgrammeUploadRoles;
use App\Features\Programmes\Services\SharedConcertProgramme;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Carbon;

class ConcertPhotoUploadStatus
{
    public function __construct(private readonly PhotoUploadProgress $progress, private readonly ProgrammeUploadRoles $roles) {}

    public function forEvent(SchedulingEvent $event): array
    {
        $programme = Programme::where('scheduling_event_id', $event->id)->first();
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $plan = $programme ? (app(SharedConcertProgramme::class)->current($programme) ?? ProgrammeUploadPlan::where('programme_id', $programme->id)
            ->when($gallery, fn ($query) => $query->whereKey($gallery->programme_upload_plan_id))
            ->latest('id')->first()) : null;
        $progress = $plan ? $this->progress->forEvent($event, $plan->uuid, false) : null;
        $people = collect($progress['photographers'] ?? $this->roles->forEvent($event)['roles'])
            ->filter(fn ($role) => ($role['media_type'] ?? 'photo') === 'photo');
        $codes = CrewRole::whereIn('uuid', $people->pluck('role_uuid'))->pluck('code', 'uuid');
        $roles = $people->map(function ($person) use ($codes): array {
            $code = $codes->get($person['role_uuid'], '');
            $short = preg_match('/-(p[0-9]*)$/i', $code, $matches) ? strtoupper($matches[1]) : $person['role_name'];
            $state = match (true) {
                ($person['status'] ?? '') === 'complete' => 'complete',
                ($person['verified_file_count'] ?? 0) + ($person['pending_file_count'] ?? 0) > 0 => 'uploading',
                default => 'waiting',
            };

            return ['role' => $short, 'state' => $state, 'label' => $short.' '.ucfirst($state),
                'verified' => $person['verified_file_count'] ?? 0, 'pending' => $person['pending_file_count'] ?? 0];
        })->values();
        $complete = $roles->isNotEmpty() && $roles->every(fn ($role) => $role['state'] === 'complete');

        $finishedAt = $complete ? $people->pluck('completed_at')->filter()->max() : null;

        return [
            'key' => $complete ? 'complete' : ($roles->contains('state', 'uploading') ? 'in_progress' : 'waiting'),
            'label' => $complete ? 'Complete' : ($roles->contains('state', 'uploading') ? 'Uploading' : 'Waiting'),
            'detail' => $roles->isEmpty() ? 'No photo roles configured.' : ($complete ? 'Every photo role has completed uploading.' : 'Waiting for the uploader to record completion for every photo role.'),
            'sourceUrl' => route('admin.photo-uploads.show', $event), 'completedAt' => $finishedAt ? Carbon::parse($finishedAt) : null, 'activity' => null,
            'photoRoles' => $roles->all(),
        ];
    }
}
