<?php

namespace App\Features\Programmes\Actions;

use App\Features\Crew\Models\CrewRole;
use App\Features\Programmes\Models\CompetitionFolderInventory;
use App\Features\Programmes\Services\CompetitionFolderProgress;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\NotifySharedProgramme;
use App\Features\Programmes\Services\ProgrammeUploadRoles;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportCompetitionFolders
{
    public function __construct(private readonly SyncProgramme $sync, private readonly CompetitionProgrammeNodes $nodes,
        private readonly ProgrammeUploadRoles $roles, private readonly CompetitionFolderProgress $progress, private readonly NotifySharedProgramme $notify) {}

    public function execute(SchedulingEvent $event, User $actor, array $input): array
    {
        return DB::transaction(function () use ($event, $actor, $input): array {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($event->event_type->value === 'competition', 422, 'Choose a competition.');
            $role = collect($this->roles->forEvent($event)['roles'])->firstWhere('role_uuid', strtolower($input['role_uuid']));
            abort_unless($role, 422, 'Choose a configured P1/P2/V event role.');
            $plan = $this->sync->execute($event, $actor, $input['revision_uuid'], $input['nodes'], null, false);
            $nodes = collect($this->nodes->nodes($plan))->keyBy('uuid');
            $folders = [];
            foreach ($input['folders'] as $folder) {
                $id = strtolower($folder['node_uuid']);
                $node = $nodes->get($id);
                abort_unless($node && in_array($node['kind'], ['section', 'item'], true), 422, 'Choose a known competition folder.');
                $section = $node['kind'] === 'section' ? $node : $nodes->get($node['parent_uuid']);
                $day = $nodes->get($section['parent_uuid']);
                abort_unless(collect($role['shifts'])->contains('date', $day['date']), 422, 'This role has no shift on a reported folder day.');
                $folders[] = ['node_uuid' => $id, 'title' => trim($folder['title'])];
            }
            $roleId = CrewRole::where('uuid', $role['role_uuid'])->value('id');
            $inventory = CompetitionFolderInventory::firstOrNew(['programme_upload_plan_id' => $plan->id,
                'crew_role_id' => $roleId, 'device_uuid' => strtolower($input['device_uuid'])]);
            $before = collect($inventory->folders ?? [])->keyBy('node_uuid');
            $baseIds = array_column($plan->snapshot['nodes'], 'uuid');
            $changes = [];
            foreach ($folders as $folder) {
                $old = $before->get($folder['node_uuid']);
                $node = $nodes[$folder['node_uuid']];
                if (($old && $this->progress->title($old['title']) !== $this->progress->title($folder['title']))
                    || (! $old && (! in_array($node['uuid'], $baseIds, true) || $this->progress->title($node['title']) !== $this->progress->title($folder['title'])))) {
                    $changes[] = ucfirst($node['kind']).' '.$node['number'].': '.$folder['title'];
                }
            }
            foreach ($before as $id => $folder) {
                if (! in_array($id, array_column($folders, 'node_uuid'), true)) {
                    $changes[] = 'No longer seen on this station: '.$folder['title'];
                }
            }
            $inventory->fill(['folders' => $folders, 'updated_by_user_id' => $actor->id, 'updated_at' => now()])->save();
            if ($changes !== []) {
                $this->notify->competition($event, $actor, $role['role_name'].' folder changes', $changes);
            }

            return $this->progress->forEvent($event, $input['revision_uuid']);
        });
    }
}
