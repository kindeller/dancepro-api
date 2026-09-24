<?php

namespace App\Features\Programmes\Services;

use App\Features\Crew\Models\CrewRole;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;

class CompetitionSectionReadiness
{
    public function __construct(private readonly CompetitionProgrammeNodes $nodes, private readonly ProgrammeUploadRoles $roles, private readonly PhotoContributionScope $scope) {}

    /** Read-only contribution readiness; this does not grant or record publication. */
    public function forPlan(SchedulingEvent $event, ProgrammeUploadPlan $plan): array
    {
        abort_unless($plan->snapshot['event_type'] === 'competition', 404);
        $programme = Programme::where('scheduling_event_id', $event->id)->whereKey($plan->programme_id)->firstOrFail();
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $current = $programme->revisions()->orderByDesc('version')->value('id') === $plan->programme_revision_id
            && (! $gallery || $gallery->programme_upload_plan_id === $plan->id);
        $nodes = collect($this->nodes->nodes($plan));
        $items = $nodes->where('kind', 'item')->filter(fn ($node) => ($node['item_kind'] ?? 'dance') !== 'break' && empty($node['merged_into_uuid']))->groupBy('parent_uuid');
        $roles = collect($this->roles->forEvent($event)['roles'])->where('media_type', 'photo');
        $scopes = $roles->mapWithKeys(fn ($role) => [$role['role_uuid'] => $this->scope->items($plan, $role)]);
        $contributions = ProgrammePhotoContribution::with('role')->where('programme_upload_plan_id', $plan->id)->get();
        $plannedIds = $event->roleRequirements->where('quantity', '>', 0)->pluck('crew_role_id')
            ->merge($contributions->pluck('crew_role_id'));
        foreach ($event->shifts as $shift) {
            $plannedIds = $plannedIds->merge($shift->roleRequirements->where('quantity', '>', 0)->pluck('crew_role_id'))
                ->merge($shift->assignments->pluck('crew_role_id'));
        }
        $inactiveRole = CrewRole::whereIn('id', $plannedIds->unique())->whereIn('code', ['competition-photographer-p1', 'competition-photographer-p2'])
            ->where('is_active', false)->exists();
        $byRole = $contributions->groupBy(fn ($contribution) => $contribution->role->uuid)
            ->map(fn ($entries) => $entries->keyBy('item_uuid'));
        $files = ProgrammePhotoFile::whereIn('programme_photo_contribution_id', $contributions->modelKeys())
            ->whereNull('duplicate_of_file_id')->select('programme_photo_contribution_id')->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'verified' AND verified_at IS NOT NULL AND storage_key IS NOT NULL AND storage_disk IS NOT NULL AND programme_photo_gallery_id = ? THEN 1 ELSE 0 END) AS received", [$gallery?->id ?? 0])
            ->groupBy('programme_photo_contribution_id')->get()->keyBy('programme_photo_contribution_id');
        $sections = [];
        foreach ($nodes->where('kind', 'section') as $section) {
            $sectionItems = ($items->get($section['uuid']) ?? collect())->pluck('uuid')->all();
            $details = [];
            foreach ($roles as $role) {
                $expected = array_values(array_intersect($sectionItems, array_keys($scopes[$role['role_uuid']])));
                if (! $expected) {
                    continue;
                }
                $complete = $waived = $verified = $pending = 0;
                foreach ($expected as $uuid) {
                    $contribution = $byRole->get($role['role_uuid'])?->get($uuid);
                    $count = $files->get($contribution?->id);
                    $received = (int) ($count?->received ?? 0);
                    $remaining = (int) ($count?->total ?? 0) - $received;
                    $verified += $received;
                    $pending += $remaining;
                    if ($contribution?->status === 'waived' && $contribution->waived_at !== null) {
                        $waived++;
                    } elseif ($contribution?->status === 'complete' && $contribution->completed_at !== null && $remaining === 0) {
                        $complete++;
                    }
                }
                $outstanding = count($expected) - $complete - $waived;
                $status = match (true) {
                    $complete === count($expected) => 'complete', $waived === count($expected) => 'waived',
                    $outstanding === 0 => 'complete_with_exceptions',
                    $complete + $waived + $verified + $pending > 0 => 'receiving', default => 'missing',
                };
                $details[] = ['role_uuid' => $role['role_uuid'], 'name' => $role['role_name'], 'status' => $status,
                    'expected' => count($expected), 'complete' => $complete, 'waived' => $waived,
                    'outstanding' => $outstanding, 'verified' => $verified, 'pending' => $pending];
            }
            $status = match (true) {
                ! $current => 'needs_attention', ! $sectionItems => 'no_items', ! $details || $inactiveRole => 'needs_setup',
                collect($details)->sum('outstanding') > 0 => 'waiting',
                collect($details)->sum('waived') > 0 => 'ready_with_exceptions', default => 'ready',
            };
            $sections[$section['uuid']] = ['status' => $status, 'label' => match ($status) {
                'needs_attention' => 'Programme needs reconciliation', 'no_items' => 'No competitors',
                'needs_setup' => 'Photo roles need setup', 'waiting' => 'Waiting for photographers',
                'ready_with_exceptions' => 'Ready with exceptions', default => 'Contributions ready',
            }, 'ready' => in_array($status, ['ready', 'ready_with_exceptions'], true), 'roles' => $details];
        }

        return $sections;
    }
}
