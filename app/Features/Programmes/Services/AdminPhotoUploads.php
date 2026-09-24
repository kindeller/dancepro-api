<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;

class AdminPhotoUploads
{
    public function __construct(private readonly PhotoUploadProgress $progress, private readonly ProgrammeUploadRoles $roles, private readonly PhotoContributionScope $scope) {}

    public function forEvent(SchedulingEvent $event, ?string $planUuid): array
    {
        $programme = Programme::where('scheduling_event_id', $event->id)->first();
        $plans = ProgrammeUploadPlan::where('programme_id', $programme?->id)->orderByDesc('id')->get();
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $plan = $planUuid ? $plans->firstWhere('uuid', $planUuid) : ($plans->firstWhere('id', $gallery?->programme_upload_plan_id) ?? $plans->first());
        abort_if($planUuid && ! $plan, 404);
        $progress = $plan ? $this->progress->forEvent($event, $plan->uuid) : null;
        $roleDetails = [];
        $scopeNodes = $plan ? app(CompetitionProgrammeNodes::class)->nodes($plan) : [];
        if ($plan) {
            $nodes = collect(app(CompetitionProgrammeNodes::class)->nodes($plan))->keyBy('uuid');
            $contributions = ProgrammePhotoContribution::with('role')->where('programme_upload_plan_id', $plan->id)->get();
            foreach ($this->roles->forEvent($event)['roles'] as $role) {
                if ($role['media_type'] !== 'photo') {
                    continue;
                }
                $items = array_keys($this->scope->items($plan, $role));
                $own = $contributions->filter(fn ($entry) => $entry->role->uuid === $role['role_uuid'])->keyBy('item_uuid');
                $roleDetails[] = ['role' => $role, 'items' => array_map(fn ($uuid) => [
                    'uuid' => $uuid, 'title' => trim(($nodes[$uuid]['folder_number'] ?? $nodes[$uuid]['number'] ?? '').' '.$nodes[$uuid]['title']),
                    'status' => $own->get($uuid)?->status ?? 'missing', 'reason' => $own->get($uuid)?->waiver_reason,
                ], $items)];
            }
        }
        $history = DB::table('programme_upload_audits as audits')->leftJoin('users', 'users.id', '=', 'audits.actor_user_id')->leftJoin('crew_roles', 'crew_roles.uuid', '=', 'audits.role_uuid')
            ->where('audits.event_uuid', $event->uuid)->whereIn('audits.action', ['contribution_waive', 'contribution_reinstate', 'waiver_reopened'])
            ->select('audits.*', 'users.name as actor_name', 'crew_roles.name as role_name')->orderByDesc('audits.id')->limit(50)->get();

        return compact('event', 'plans', 'plan', 'progress', 'roleDetails', 'history', 'scopeNodes');
    }
}
