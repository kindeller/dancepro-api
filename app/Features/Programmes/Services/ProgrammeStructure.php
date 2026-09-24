<?php

namespace App\Features\Programmes\Services;

use Illuminate\Validation\ValidationException;

class ProgrammeStructure
{
    public function validate(array $nodes, bool $competition, bool $confirming = false): void
    {
        $byUuid = collect($nodes)->keyBy('uuid');
        $numbers = [];
        $dates = [];
        $errors = [];
        foreach ($nodes as $index => $node) {
            $kind = $node['kind'];
            $parent = $byUuid->get($node['parent_uuid'] ?? '');
            $allowedParent = $competition
                ? ['day' => null, 'section' => 'day', 'item' => 'section']
                : ['group' => null, 'item' => 'group'];
            $validParent = array_key_exists($kind, $allowedParent)
                && (($allowedParent[$kind] === null && empty($node['parent_uuid']))
                    || ($parent && $parent['kind'] === $allowedParent[$kind])
                    || (! $competition && $kind === 'item' && empty($node['parent_uuid'])));
            if (! $validParent) {
                $errors["nodes.$index.parent_uuid"] = 'Choose a valid parent within this programme.';
            }
            if (! $confirming) {
                continue;
            }
            if (blank($node['title'] ?? null)) {
                $errors["nodes.$index.title"] = 'Every row needs a title or printed label before confirmation.';
            }
            if ($kind === 'day') {
                $date = $node['date'] ?? null;
                if (! $date || isset($dates[$date])) {
                    $errors["nodes.$index.date"] = 'Each programme day needs a different date.';
                }
                $dates[$date ?? ''] = true;
            }
            if ($competition && in_array($kind, ['section', 'item'], true)) {
                $number = $node['number'] ?? null;
                // Sections are event-wide; competitor numbering belongs to its section.
                $key = $kind.':'.($kind === 'section' ? '' : ($node['parent_uuid'] ?? '')).':'.strtolower(ltrim($number ?? '', '0'));
                if (blank($number) || isset($numbers[$key])) {
                    $errors["nodes.$index.number"] = $kind === 'section'
                        ? 'Section numbers must be present and unique across the event.'
                        : 'Competitor numbers must be present and unique within their section.';
                }
                $numbers[$key] = true;
            }
        }
        if ($confirming && ! collect($nodes)->contains(fn ($node) => $node['kind'] === 'item' && ($node['item_kind'] ?? 'dance') !== 'break')) {
            $errors['nodes'] = 'Add at least one performance before confirming.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
