<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeRevision;
use App\Features\Programmes\Services\ConcertFolderSequence;
use App\Features\Programmes\Services\ProgrammeStructure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmProgramme
{
    public function __construct(private readonly ProgrammeStructure $structure, private readonly ConcertFolderSequence $folders) {}

    public function execute(Programme $programme, User $user, int $version): ProgrammeRevision
    {
        return DB::transaction(function () use ($programme, $user, $version): ProgrammeRevision {
            $programme = Programme::whereKey($programme->id)->lockForUpdate()->firstOrFail();
            abort_unless($programme->draft_version === $version, 409, 'This programme changed in another window. Reload before confirming.');
            $existing = $programme->revisions()->where('version', $version)->first();
            if ($existing) {
                $needsUpdatedConcertNames = $programme->event->event_type->value === 'concert'
                    && collect($existing->snapshot['nodes'])->contains(fn ($node) => $node['kind'] === 'item' && (! array_key_exists('folder_number', $node) || ! array_key_exists('folder_title', $node)));
                if (! $needsUpdatedConcertNames) {
                    return $existing;
                }
                // Explicit reapproval upgrades the contract in a new revision, never in place.
                $programme->update(['draft_version' => ++$version, 'updated_by_user_id' => $user->id]);
            }
            $nodes = $programme->draftNodes();
            $event = $programme->event;
            $this->structure->validate($nodes, $event->event_type->value === 'competition', true);
            if ($event->event_type->value === 'concert') {
                $nodes = $this->folders->apply($nodes);
            }

            return $programme->revisions()->create([
                'version' => $version, 'programme_source_id' => $programme->programme_source_id,
                'confirmed_by_user_id' => $user->id, 'confirmed_at' => now(),
                'snapshot' => [
                    'schema_version' => 1, 'event_uuid' => $event->uuid, 'event_code' => $event->eventCode(),
                    'event_name' => $event->name, 'event_type' => $event->event_type->value,
                    'programme_uuid' => $programme->uuid, 'version' => $version,
                    'source_uuid' => $programme->source?->uuid, 'nodes' => $nodes,
                ],
            ]);
        });
    }
}
