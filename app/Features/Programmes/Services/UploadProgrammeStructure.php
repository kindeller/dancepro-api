<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ProgrammeNode;
use Illuminate\Validation\ValidationException;

class UploadProgrammeStructure
{
    public function __construct(private readonly ProgrammeStructure $structure, private readonly ConcertFolderTitle $titles, private readonly CompetitionProgrammeNodes $competitionNodes) {}

    public function validate(array $nodes, array $base, int $programmeId): array
    {
        // Canonical key order and absent/null equivalence make retries idempotent.
        $nodes = array_map($this->canonical(...), $nodes);
        $original = array_map($this->canonical(...), $base['nodes']);
        $byId = array_column($nodes, null, 'uuid');
        $competition = $base['event_type'] === 'competition';
        if ($competition) {
            foreach ($original as $node) {
                if (($byId[$node['uuid']] ?? null) !== $node) {
                    $this->fail('Keep approved competition identities and metadata; report folder-name differences separately.');
                }
            }
            $this->competitionNodes->validateAdditions($nodes, $base);
        }
        foreach ($original as $node) {
            $current = $byId[$node['uuid']] ?? null;
            if (! $current || $current['kind'] !== $node['kind'] || $current['item_kind'] !== $node['item_kind']) {
                $this->fail('Keep all original identities and item types; combine items using an alias.');
            }
        }
        if (ProgrammeNode::whereIn('uuid', array_keys($byId))->where('programme_id', '!=', $programmeId)->exists()) {
            $this->fail('An item identity belongs to another event.');
        }
        $this->structure->validate($nodes, $competition, true);
        $active = 0;
        $numbers = [];
        foreach ($nodes as $node) {
            $target = $node['merged_into_uuid'];
            $performance = $node['kind'] === 'item' && $node['item_kind'] !== 'break';
            if ($target !== null) {
                $destination = $byId[$target] ?? null;
                if ($competition || ! $performance || $target === $node['uuid'] || ! $destination
                    || $destination['kind'] !== 'item' || $destination['item_kind'] === 'break'
                    || $destination['merged_into_uuid'] !== null) {
                    $this->fail('Combined items must point directly to an active performance in this programme.');
                }
            }
            if ($performance && $target === null) {
                $active++;
                if (! $competition) {
                    $number = str_pad((string) (int) $node['folder_number'], 2, '0', STR_PAD_LEFT);
                    $numbers[] = (int) $number;
                    $title = $this->titles->clean($node['title']);
                    // Older prepared folders retain accents until explicitly renamed in the uploader.
                    $acceptedTitles = [$title, $this->titles->clean($node['title'], stripAccents: false)];
                    if ($node['folder_number'] !== $number || ! in_array($node['folder_title'], $acceptedTitles, true)
                        || $title === '' || strlen($number.' '.$node['folder_title']) > 220) {
                        $this->fail('Concert folder names must use continuous numbers starting at 01 and cleaned titles.');
                    }
                }
            } elseif (! $competition && ($node['folder_number'] !== null || $node['folder_title'] !== null)) {
                $this->fail('Groups, breaks and combined aliases cannot have media folders.');
            }
        }
        if ($active === 0) {
            $this->fail('Keep at least one active performance.');
        }

        if (! $competition) {
            sort($numbers);
            if ($numbers !== range(1, $active)) {
                $this->fail('Concert folder numbers must be continuous and unique, starting at 01.');
            }
        }

        $sourceNodes = array_column($base['nodes'], null, 'uuid');

        return array_map(fn ($node) => array_replace($sourceNodes[$node['uuid']] ?? [], $node), $nodes);
    }

    private function canonical(array $node): array
    {
        $result = [];
        foreach (['uuid', 'parent_uuid', 'kind', 'number', 'title', 'date', 'class_name', 'item_kind', 'folder_number', 'folder_title', 'merged_into_uuid'] as $key) {
            $value = $node[$key] ?? null;
            $result[$key] = $value !== null && in_array($key, ['uuid', 'parent_uuid', 'merged_into_uuid'], true) ? strtolower($value) : $value;
        }

        return $result;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['nodes' => $message]);
    }
}
