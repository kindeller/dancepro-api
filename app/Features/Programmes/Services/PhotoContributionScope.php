<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ProgrammeUploadPlan;

class PhotoContributionScope
{
    /** Active items mapped to the photographer's current assignment identities. */
    public function items(ProgrammeUploadPlan $plan, array $person): array
    {
        $nodes = collect(app(CompetitionProgrammeNodes::class)->nodes($plan))->keyBy(fn ($node) => strtolower($node['uuid']));
        $items = [];
        foreach ($nodes as $uuid => $node) {
            if ($node['kind'] !== 'item' || ($node['item_kind'] ?? null) === 'break' || ($node['merged_into_uuid'] ?? null) !== null) {
                continue;
            }
            $shifts = collect($person['shifts']);
            if ($plan->snapshot['event_type'] === 'competition') {
                $section = $nodes->get(strtolower($node['parent_uuid'] ?? ''));
                $day = $nodes->get(strtolower($section['parent_uuid'] ?? ''));
                $shifts = $shifts->where('date', $day['date'] ?? null);
            }
            if ($shifts->isNotEmpty()) {
                $items[$uuid] = $shifts->pluck('assignment_uuid')->sort()->values()->all();
            }
        }

        return $items;
    }
}
