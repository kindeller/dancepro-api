<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\CompetitionFolderInventory;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;

class CompetitionFolderProgress
{
    public function __construct(private readonly CompetitionProgrammeNodes $nodes, private readonly ProgrammeUploadRoles $roles, private readonly ConcertFolderTitle $titles) {}

    public function title(string $value): string
    {
        return mb_strtolower($this->titles->clean($value));
    }

    public function forEvent(SchedulingEvent $event, string $revisionUuid): array
    {
        abort_unless($event->event_type->value === 'competition', 422, 'Choose a competition.');
        $programme = Programme::where('scheduling_event_id', $event->id)->firstOrFail();
        $revision = $programme->revisions()->orderByDesc('version')->firstOrFail();
        abort_unless(strtolower($revisionUuid) === $revision->uuid, 409, 'Prepare the current competition programme before checking folders.');
        $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->where('programme_revision_id', $revision->id)->orderBy('id')->first();
        $nodes = collect($plan ? $this->nodes->nodes($plan) : $revision->snapshot['nodes'])->keyBy('uuid');
        $roles = $this->roles->forEvent($event)['roles'];
        $inventories = $plan ? CompetitionFolderInventory::with('role')->where('programme_upload_plan_id', $plan->id)->get() : collect();
        $byRole = $inventories->groupBy(fn ($inventory) => $inventory->role->uuid);
        $observed = [];
        $checked = [];
        foreach ($byRole as $roleUuid => $stations) {
            $observed[$roleUuid] = $stations->flatMap(fn ($inventory) => $inventory->folders)->groupBy(fn ($entry) => strtolower($entry['node_uuid']));
            $checked[$roleUuid] = $stations->max('updated_at')?->toIso8601String();
        }
        $rows = [];
        foreach ($nodes as $node) {
            if (! in_array($node['kind'], ['section', 'item'], true) || ($node['item_kind'] ?? null) === 'break') {
                continue;
            }
            $section = $node['kind'] === 'section' ? $node : $nodes->get($node['parent_uuid']);
            $day = $nodes->get($section['parent_uuid']);
            $states = [];
            foreach ($roles as $role) {
                if (! collect($role['shifts'])->contains('date', $day['date'])) {
                    continue;
                }
                $matches = ($observed[$role['role_uuid']] ?? collect())->get($node['uuid'], collect());
                $names = $matches->pluck('title')->unique()->values()->all();
                $status = ! isset($observed[$role['role_uuid']]) ? 'not_checked' : ($matches->isEmpty() ? 'not_seen' : 'matched');
                if ($matches->contains(fn ($entry) => $this->title($entry['title']) !== $this->title($node['title']))) {
                    $status = 'name_difference';
                }
                $states[] = ['role_uuid' => $role['role_uuid'], 'role_name' => $role['role_name'], 'status' => $status,
                    'names' => $names, 'checked_at' => $checked[$role['role_uuid']] ?? null];
            }
            $rows[] = ['uuid' => $node['uuid'], 'kind' => $node['kind'], 'date' => $day['date'],
                'section_number' => $section['number'], 'number' => $node['number'], 'title' => $node['title'], 'roles' => $states];
        }

        return ['event_uuid' => $event->uuid, 'revision_uuid' => $revision->uuid, 'checked_at' => now()->toIso8601String(), 'items' => $rows];
    }
}
