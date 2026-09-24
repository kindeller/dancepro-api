<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeNode;
use App\Features\Programmes\Services\ProgrammeStructure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProgrammeDraft
{
    public function __construct(private readonly ProgrammeStructure $structure) {}

    public function execute(Programme $programme, User $user, int $version, array $nodes): Programme
    {
        return DB::transaction(function () use ($programme, $user, $version, $nodes): Programme {
            $programme = Programme::whereKey($programme->id)->lockForUpdate()->firstOrFail();
            abort_unless($programme->draft_version === $version, 409, 'This programme changed in another window. Reload before saving.');
            $nodes = array_map(function ($node) {
                $node['uuid'] = strtolower($node['uuid']);
                $node['parent_uuid'] = empty($node['parent_uuid']) ? null : strtolower($node['parent_uuid']);

                return $node;
            }, $nodes);
            $this->structure->validate($nodes, $programme->event->event_type->value === 'competition');
            $existing = ProgrammeNode::whereIn('uuid', array_column($nodes, 'uuid'))->get()->keyBy('uuid');
            foreach ($nodes as $index => $node) {
                $record = $existing->get($node['uuid']);
                if ($record && ($record->programme_id !== $programme->id || $record->kind !== $node['kind'])) {
                    throw ValidationException::withMessages(["nodes.$index.uuid" => 'An item identity cannot change type or belong to another event.']);
                }
            }
            $programme->nodes()->update(['is_active' => false]);
            foreach ($nodes as $index => $node) {
                $programme->nodes()->updateOrCreate(['uuid' => $node['uuid']], [
                    'kind' => $node['kind'], 'data' => $node, 'sort_order' => $index, 'is_active' => true,
                ]);
            }
            $programme->update(['draft_version' => $version + 1, 'updated_by_user_id' => $user->id]);

            return $programme;
        });
    }
}
