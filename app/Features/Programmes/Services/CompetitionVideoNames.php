<?php

namespace App\Features\Programmes\Services;

class CompetitionVideoNames
{
    public function itemUuid(string $filename, string $eventCode, array $nodes): ?string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        if (! preg_match('/^(.+)_([0-9]+)_([0-9]+)[a-z]?$/i', $stem, $parts)
            || strcasecmp($parts[1], $eventCode) !== 0) {
            return null;
        }
        $sections = collect($nodes)->filter(fn (array $node) => ($node['kind'] ?? null) === 'section'
            && CompetitionProgrammeNodes::number((string) ($node['number'] ?? '')) === CompetitionProgrammeNodes::number($parts[2]));
        if ($sections->count() !== 1) {
            return null;
        }
        $items = collect($nodes)->filter(fn (array $node) => ($node['kind'] ?? null) === 'item'
            && ($node['parent_uuid'] ?? null) === $sections->first()['uuid']
            && ($node['merged_into_uuid'] ?? null) === null
            && CompetitionProgrammeNodes::number((string) ($node['number'] ?? '')) === CompetitionProgrammeNodes::number($parts[3]));

        return $items->count() === 1 ? $items->first()['uuid'] : null;
    }
}
