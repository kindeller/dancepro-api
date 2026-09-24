<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\PhotoContributionScope;
use App\Features\Programmes\Services\ProgrammeUploadRoles;
use App\Features\Programmes\Services\UploadAudit;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResolvePhotoContributionException
{
    public function __construct(private readonly RegisterPhotoContributions $register, private readonly ProgrammeUploadRoles $roles,
        private readonly PhotoContributionScope $scope, private readonly UploadAudit $audit) {}

    public function execute(SchedulingEvent $event, User $actor, array $input): int
    {
        abort_unless($actor->canAccessAdmin(), 403);

        return DB::transaction(function () use ($event, $actor, $input): int {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $programme = Programme::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->where('uuid', $input['plan_uuid'])->firstOrFail();
            $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
            abort_if($gallery && $gallery->programme_upload_plan_id !== $plan->id, 409, 'Resolve the working programme conflict before changing contribution requirements.');
            $role = collect($this->roles->forEvent($event)['roles'])->firstWhere('role_uuid', $input['role_uuid']);
            abort_unless($role && $role['media_type'] === 'photo', 422, 'Choose a photo role for this event.');
            $items = array_keys($this->scope->items($plan, $role));
            if (! empty($input['scope_uuid'])) {
                $scope = strtolower($input['scope_uuid']);
                $nodes = collect(app(CompetitionProgrammeNodes::class)->nodes($plan))->keyBy(fn ($node) => strtolower($node['uuid']));
                abort_unless($nodes->has($scope), 422, 'Choose a scope from this programme.');
                $items = array_values(array_filter($items, function ($uuid) use ($nodes, $scope): bool {
                    $seen = [];
                    while ($uuid && ! isset($seen[$uuid])) {
                        if ($uuid === $scope) {
                            return true;
                        }
                        $seen[$uuid] = true;
                        $uuid = strtolower($nodes->get($uuid)['parent_uuid'] ?? '');
                    }

                    return false;
                }));
            }
            abort_if($items === [], 422, 'No active items for this role in the selected scope.');
            $links = $this->register->execute($event, $actor, $input + ['item_uuids' => $items]);
            $contributions = ProgrammePhotoContribution::whereIn('uuid', array_column($links['contributions'], 'uuid'))->get();
            $changed = [];
            foreach ($contributions as $contribution) {
                if ($input['action'] === 'waive') {
                    if ($contribution->status === 'complete' || ($contribution->status === 'waived' && $contribution->waiver_reason === $input['reason'])) {
                        continue;
                    }
                    $contribution->update(['status' => 'waived', 'waiver_reason' => $input['reason'], 'waived_at' => now(),
                        'waived_by_user_id' => $actor->id, 'completed_at' => null, 'completed_by_user_id' => null]);
                } else {
                    if ($contribution->status !== 'waived') {
                        continue;
                    }
                    $hasFiles = ProgrammePhotoFile::where('programme_photo_contribution_id', $contribution->id)->exists();
                    $contribution->update(['status' => $hasFiles ? 'receiving' : 'awaiting_files',
                        'waiver_reason' => null, 'waived_at' => null, 'waived_by_user_id' => null]);
                }
                $changed[] = $contribution->item_uuid;
            }
            if ($changed !== []) {
                $this->audit->record($event, $input['role_uuid'], $actor, 'contribution_'.$input['action'], null, [
                    'plan_uuid' => $plan->uuid, 'item_uuids' => $changed, 'reason' => $input['reason'],
                ]);
            }

            return count($changed);
        });
    }
}
