<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\PhotoContributionScope;
use App\Features\Programmes\Services\ProgrammeUploadRoles;
use App\Features\Programmes\Services\UploadAudit;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FinishPhotoUploads
{
    public function __construct(private readonly RegisterPhotoContributions $register, private readonly ProgrammeUploadRoles $photographers, private readonly PhotoContributionScope $scope) {}

    public function execute(SchedulingEvent $event, User $user, array $input): array
    {
        return DB::transaction(function () use ($event, $user, $input): array {
            // Same lock order as registration and receipt: later reservations reopen
            // completion only after this transaction has finished.
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $programme = Programme::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->where('uuid', $input['plan_uuid'])->firstOrFail();
            $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
            abort_if($gallery && $gallery->programme_upload_plan_id !== $plan->id, 409, 'This event uses a different working programme. Completion has not been recorded.');
            $person = collect($this->photographers->forEvent($event)['roles'])->first(fn ($entry) => $entry['role_uuid'] === $input['role_uuid']);
            abort_unless($person && $person['media_type'] === 'photo', 409, 'Choose a photo role configured for this event.');
            $items = array_keys($this->scope->items($plan, $person));
            abort_if($items === [], 409, 'No programme items match this photographer’s shifts.');
            // Includes empty items once per role, without repetitive folder prompts.
            $links = $this->register->execute($event, $user, $input + ['item_uuids' => $items]);
            $contributions = ProgrammePhotoContribution::whereIn('uuid', array_column($links['contributions'], 'uuid'))->get();
            $files = ProgrammePhotoFile::whereIn('programme_photo_contribution_id', $contributions->where('status', '!=', 'waived')->modelKeys());
            abort_if((clone $files)->where(function ($query): void {
                $query->where('status', '!=', 'verified')->orWhereNull('verified_at')->orWhereNull('storage_key');
            })->exists(), 409, 'Some reserved photos have not been verified. Resume uploading before finishing this event.');
            $now = now();
            foreach ($contributions as $contribution) {
                if (! in_array($contribution->status, ['complete', 'waived'], true)) {
                    $contribution->update(['status' => 'complete', 'completed_at' => $now, 'completed_by_user_id' => $user->id]);
                }
            }

            app(UploadAudit::class)->record($event, $input['role_uuid'], $user, 'role_completed', null, ['plan_uuid' => $plan->uuid, 'contribution_count' => $contributions->count()]);

            return [
                'event_uuid' => $event->uuid, 'plan_uuid' => $plan->uuid,
                'crew_uuid' => $input['crew_uuid'] ?? null, 'role_uuid' => $input['role_uuid'],
                'status' => 'complete', 'contribution_count' => $contributions->count(),
                'verified_file_count' => $files->whereNull('duplicate_of_file_id')->count(), 'visibility' => 'private',
                'waived_item_count' => $contributions->where('status', 'waived')->count(),
                'completed_at' => ($contributions->max('completed_at') ?? $contributions->max('waived_at'))->toIso8601String(),
            ];
        });
    }
}
