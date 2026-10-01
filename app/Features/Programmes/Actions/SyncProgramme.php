<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\CompetitionProgrammeAddition;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\NotifySharedProgramme;
use App\Features\Programmes\Services\ProgrammeStructure;
use App\Features\Programmes\Services\SharedConcertProgramme;
use App\Features\Programmes\Services\UploadProgrammeStructure;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncProgramme
{
    public function __construct(private readonly UploadProgrammeStructure $structure, private readonly SharedConcertProgramme $shared, private readonly NotifySharedProgramme $notify) {}

    public function execute(SchedulingEvent $event, User $user, string $revisionUuid, array $nodes, ?string $basePlanUuid = null, bool $notifyCompetition = true): ProgrammeUploadPlan
    {
        return DB::transaction(function () use ($event, $user, $revisionUuid, $nodes, $basePlanUuid, $notifyCompetition): ProgrammeUploadPlan {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($event->cancelled_at !== null, 409, 'This event is cancelled.');
            $programme = Programme::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $revision = $programme->revisions()->orderByDesc('version')->first();
            abort_unless($revision && strtolower($revision->uuid) === strtolower($revisionUuid), 409,
                'A newer or different programme is approved. Prepare that version separately before syncing; your local photos are kept.');
            $nodes = $this->structure->validate($nodes, $revision->snapshot, $programme->id);
            if (($revision->snapshot['capture_mode'] ?? null) === 'dress_rehearsal') {
                abort_unless(count($nodes) === 1 && $nodes[0]['uuid'] === $revision->snapshot['nodes'][0]['uuid']
                    && $nodes[0]['title'] === 'Dress Rehearsal Portraits'
                    && ($nodes[0]['parent_uuid'] ?? null) === null && ($nodes[0]['merged_into_uuid'] ?? null) === null,
                    422, 'Dress rehearsal portraits use one photo folder. Prepare this event again to restore its folder.');
            }
            if ($revision->snapshot['event_type'] === 'competition') {
                $originalIDs = array_column($revision->snapshot['nodes'], 'uuid');
                $incoming = collect($nodes)->keyBy('uuid');
                $baseNodes = array_map(fn ($id) => $incoming[$id], $originalIDs);
                // All roles keep the same immutable plan. Additions extend its effective nodes.
                $baseSnapshot = array_replace($revision->snapshot, ['revision_uuid' => $revision->uuid, 'nodes' => $baseNodes]);
                $baseDigest = hash('sha256', json_encode($baseSnapshot, JSON_THROW_ON_ERROR));
                $plan = ProgrammeUploadPlan::firstOrCreate(['programme_id' => $programme->id, 'digest' => $baseDigest], [
                    'programme_revision_id' => $revision->id, 'created_by_user_id' => $user->id, 'snapshot' => $baseSnapshot,
                ]);
                $before = app(CompetitionProgrammeNodes::class)->nodes($plan);
                $combined = collect($before)->keyBy('uuid');
                foreach ($nodes as $node) {
                    if (! $combined->has($node['uuid'])) {
                        $combined->put($node['uuid'], $node);
                    }
                }
                abort_if($combined->count() > 10000, 422, 'The competition programme exceeds the supported item count.');
                app(ProgrammeStructure::class)->validate($combined->values()->all(), true, true);
                $added = [];
                foreach ($nodes as $node) {
                    if (in_array($node['uuid'], $originalIDs, true)) {
                        continue;
                    }
                    $addition = CompetitionProgrammeAddition::firstOrCreate(['programme_upload_plan_id' => $plan->id, 'node_uuid' => $node['uuid']],
                        ['node' => $node, 'created_by_user_id' => $user->id]);
                    // A second station can report another title, never reparent the identity.
                    abort_unless(($addition->node['parent_uuid'] ?? null) === ($node['parent_uuid'] ?? null), 422, 'This competition section/competitor belongs to another parent.');
                    if ($addition->wasRecentlyCreated) {
                        $added[] = $node;
                    }
                }
                if ($notifyCompetition && $added !== []) {
                    $this->notify->competition($event, $user, 'Competition folders added', array_column($added, 'title'));
                }

                return $plan;
            }
            $snapshot = array_replace($revision->snapshot, ['revision_uuid' => $revision->uuid, 'nodes' => $nodes]);
            $digest = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));

            $concert = $revision->snapshot['event_type'] === 'concert';
            $current = $concert ? $this->shared->current($programme) : null;
            if ($current && $current->digest === $digest) {
                return $current;
            }
            if ($current) {
                abort_unless(strtolower($basePlanUuid ?? '') === $current->uuid, 409,
                    'Programme update available. Open Programme and load the shared version before sharing changes.');
                abort_if(ProgrammePhotoContribution::where('programme_upload_plan_id', $current->id)->exists(), 409,
                    'Uploads already use the confirmed programme. Keep that version; later structural corrections need administrator reconciliation.');
                // Previously shared identities, including additions and aliases, survive later edits.
                $this->structure->validate($nodes, $current->snapshot, $programme->id);
            }
            $plan = ProgrammeUploadPlan::firstOrCreate(['programme_id' => $programme->id, 'digest' => $digest], [
                'programme_revision_id' => $revision->id, 'created_by_user_id' => $user->id, 'snapshot' => $snapshot,
            ]);
            if ($concert) {
                $programme->update(['shared_upload_plan_id' => $plan->id]);
                $this->notify->send($event, $user, $current?->snapshot['nodes'] ?? $revision->snapshot['nodes'], $nodes);
            }

            return $plan;
        });
    }
}
