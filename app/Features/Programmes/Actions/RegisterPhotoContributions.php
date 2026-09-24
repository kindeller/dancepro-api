<?php

namespace App\Features\Programmes\Actions;

use App\Features\Crew\Models\CrewRole;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\ProgrammeUploadRoles;
use App\Features\Programmes\Services\SharedConcertProgramme;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterPhotoContributions
{
    public function __construct(private readonly ProgrammeUploadRoles $photographers, private readonly SharedConcertProgramme $shared) {}

    public function execute(SchedulingEvent $event, User $user, array $input): array
    {
        return DB::transaction(function () use ($event, $user, $input): array {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($event->cancelled_at !== null, 409, 'This event is cancelled.');
            $programme = Programme::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->where('uuid', $input['plan_uuid'])->firstOrFail();
            $revision = $programme->revisions()->orderByDesc('version')->first();
            abort_unless($revision && $plan->programme_revision_id === $revision->id, 409,
                'A newer programme is approved. Prepare that version separately before connecting queued items.');
            abort_if($revision->snapshot['event_type'] === 'concert' && $this->shared->current($programme)?->id !== $plan->id, 409, 'Programme update available. Load the shared concert programme before uploading.');
            $role = CrewRole::where('uuid', $input['role_uuid'])->firstOrFail();
            $roster = $this->photographers->forEvent($event);
            $person = collect($roster['roles'])->first(fn ($entry) => $entry['role_uuid'] === $role->uuid);
            abort_unless($person && $person['media_type'] === 'photo', 409, 'Choose a photo role configured for this event.');
            $nodes = collect(app(CompetitionProgrammeNodes::class)->nodes($plan))->keyBy(fn ($node) => strtolower($node['uuid']));
            $assignments = [];
            foreach ($input['item_uuids'] as $id) {
                $id = strtolower($id);
                $node = $nodes->get($id);
                if (! $node || $node['kind'] !== 'item' || ($node['item_kind'] ?? null) === 'break' || ($node['merged_into_uuid'] ?? null) !== null) {
                    throw ValidationException::withMessages(['item_uuids' => 'Choose active performance items from this synced programme.']);
                }
                $shifts = collect($person['shifts']);
                if ($plan->snapshot['event_type'] === 'competition') {
                    $section = $nodes->get(strtolower($node['parent_uuid'] ?? ''));
                    $day = $nodes->get(strtolower($section['parent_uuid'] ?? ''));
                    abort_unless($section && $section['kind'] === 'section' && $day && $day['kind'] === 'day' && ! empty($day['date']), 409,
                        'This item has no valid programme day.');
                    $shifts = $shifts->where('date', $day['date']);
                }
                abort_if($shifts->isEmpty(), 409, 'That event role has no shift on a queued item’s programme day.');
                $assignments[$id] = $shifts->pluck('assignment_uuid')->sort()->values()->all();
            }
            // Validate the entire batch before creating any contribution. The
            // programme lock serializes retries, and the unique key is a backstop.
            $links = [];
            foreach ($assignments as $id => $assignmentUuids) {
                $contribution = ProgrammePhotoContribution::firstOrCreate([
                    'programme_upload_plan_id' => $plan->id, 'item_uuid' => $id,
                    'crew_role_id' => $role->id,
                ], ['registered_by_user_id' => $user->id, 'assignment_uuids' => $assignmentUuids, 'status' => 'awaiting_files']);
                $links[] = ['uuid' => $contribution->uuid, 'item_uuid' => $id, 'status' => $contribution->status];
            }

            return [
                'event_uuid' => $event->uuid, 'plan_uuid' => $plan->uuid,
                'crew_uuid' => $input['crew_uuid'] ?? null, 'role_uuid' => $role->uuid, 'contributions' => $links,
            ];
        });
    }
}
