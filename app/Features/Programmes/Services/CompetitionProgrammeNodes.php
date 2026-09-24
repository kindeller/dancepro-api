<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\CompetitionProgrammeAddition;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class CompetitionProgrammeNodes
{
    public function nodes(ProgrammeUploadPlan $plan): array
    {
        return array_merge($plan->snapshot['nodes'], $plan->snapshot['event_type'] === 'competition'
            ? CompetitionProgrammeAddition::where('programme_upload_plan_id', $plan->id)->orderBy('id')->pluck('node')->all() : []);
    }

    public static function number(string $value): string
    {
        $value = strtolower(ltrim($value, '0'));

        return $value === '' ? '0' : $value;
    }

    public static function identity(string $programmeUuid, string $kind, string $number, ?string $sectionUuid = null): string
    {
        $name = 'competition/'.$kind.'/'.($sectionUuid ? strtolower($sectionUuid).'/' : '').self::number($number);

        return Uuid::uuid5($programmeUuid, $name)->toString();
    }

    public function validateAdditions(array $nodes, array $base): void
    {
        $original = collect($base['nodes'])->keyBy('uuid');
        $byId = collect($nodes)->keyBy('uuid');
        foreach ($nodes as $node) {
            if ($original->has($node['uuid'])) {
                continue;
            }
            $parent = $byId->get($node['parent_uuid'] ?? '');
            $kind = $node['kind'];
            if (! in_array($kind, ['section', 'item'], true) || ! $parent
                || ($node['merged_into_uuid'] ?? null) !== null
                || ($node['item_kind'] ?? null) !== null && $node['item_kind'] !== 'dance'
                || ($kind === 'section' && ! $original->has($parent['uuid']))
                || $node['uuid'] !== self::identity($base['programme_uuid'], $kind, $node['number'] ?? '', $kind === 'item' ? $parent['uuid'] : null)) {
                throw ValidationException::withMessages(['nodes' => 'Extra competition folders must use stable section/competitor identities within an approved day.']);
            }
        }
    }
}
