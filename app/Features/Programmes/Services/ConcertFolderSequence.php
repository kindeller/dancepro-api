<?php

namespace App\Features\Programmes\Services;

use Illuminate\Validation\ValidationException;

class ConcertFolderSequence
{
    public function __construct(private readonly ConcertFolderTitle $titles) {}

    public function apply(array $nodes): array
    {
        $children = collect($nodes)->groupBy(fn ($node) => $node['parent_uuid'] ?? '');
        $numbers = [];
        $next = 1;
        $visit = function (string $parent) use (&$visit, &$numbers, &$next, $children): void {
            foreach ($children->get($parent, []) as $node) {
                if ($node['kind'] === 'item') {
                    $numbers[$node['uuid']] = ($node['item_kind'] ?? 'dance') === 'break'
                        ? null : str_pad((string) $next++, 2, '0', STR_PAD_LEFT);
                } else {
                    $visit($node['uuid']);
                }
            }
        };
        $visit('');

        foreach ($nodes as $index => $node) {
            if ($node['kind'] === 'item' && ($node['item_kind'] ?? 'dance') !== 'break' && $this->titles->clean($node['title'] ?? null) === '') {
                throw ValidationException::withMessages(["nodes.$index.title" => 'The title needs a letter or number to create a folder name.']);
            }
        }

        return array_map(function (array $node) use ($numbers): array {
            if ($node['kind'] === 'item') {
                $node['folder_number'] = $numbers[$node['uuid']] ?? null;
                $node['folder_title'] = $node['folder_number'] === null ? null : $this->titles->clean($node['title'] ?? null);
            }

            return $node;
        }, $nodes);
    }
}
