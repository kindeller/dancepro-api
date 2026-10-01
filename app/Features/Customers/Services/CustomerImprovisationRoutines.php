<?php

namespace App\Features\Customers\Services;

use App\Features\Programmes\Services\CompetitionFolderCovers;
use App\Features\Programmes\Services\CompetitionGallery;
use App\Features\Scheduling\Models\SchedulingEvent;

class CustomerImprovisationRoutines
{
    public function __construct(private readonly CompetitionGallery $gallery) {}

    public function forEvent(SchedulingEvent $event): array
    {
        $data = $this->gallery->browse($event, null);
        $nodes = $data['nodes'];
        $ready = $data['sectionReadiness'];
        $routines = $nodes->where('kind', 'item')->filter(function (array $item) use ($nodes, $ready): bool {
            $section = $nodes->get($item['parent_uuid']);

            return ($ready[$item['parent_uuid']]['ready'] ?? false)
                && preg_match('/\bimprov(?:isation|ization)?\b/iu', ($section['title'] ?? '').' '.($item['title'] ?? '')) === 1;
        })->map(function (array $item) use ($nodes): array {
            $section = $nodes->get($item['parent_uuid']);
            $day = $nodes->get($section['parent_uuid'] ?? '');

            return ['uuid' => $item['uuid'], 'label' => $item['label'],
                'section' => $section['label'] ?? null, 'day' => $day['label'] ?? null];
        })->values()->all();

        $routines = app(CompetitionFolderCovers::class)->apply(array_map(fn (array $routine) => $routine + ['kind' => 'item'], $routines), $nodes, $event);

        return ['event' => $event, 'routines' => $routines, 'title' => $data['presentation']['title']];
    }
}
