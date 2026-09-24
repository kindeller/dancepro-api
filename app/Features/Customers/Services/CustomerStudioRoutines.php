<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Programmes\Models\CompetitionRoutineStudio;
use App\Features\Programmes\Services\CompetitionGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class CustomerStudioRoutines
{
    public function __construct(private readonly CompetitionGallery $gallery) {}

    public function forEvent(User $user, SchedulingEvent $event): array
    {
        $data = $this->gallery->browse($event, null);
        $studios = CustomerStudio::where('user_id', $user->id)
            ->whereNotNull('access_verified_at')->whereHas('studio', fn ($query) => $query->where('status', 'active'))
            ->with('studio')->get()->pluck('studio')->sortBy('name')->values();
        $assignments = CompetitionRoutineStudio::where('scheduling_event_id', $event->id)
            ->whereIn('studio_id', $studios->pluck('id'))->get()->keyBy('item_uuid');
        $nodes = $data['nodes'];
        $ready = $data['sectionReadiness'];
        $routines = $nodes->where('kind', 'item')->filter(fn (array $item) => ($ready[$item['parent_uuid']]['ready'] ?? false) && $assignments->has($item['uuid']))
            ->map(function (array $item) use ($nodes, $assignments, $studios): array {
                $section = $nodes->get($item['parent_uuid']);
                $day = $nodes->get($section['parent_uuid'] ?? '');
                $studio = $studios->firstWhere('id', $assignments->get($item['uuid'])->studio_id);

                return ['uuid' => $item['uuid'], 'label' => $item['label'], 'section' => $section['label'] ?? null,
                    'day' => $day['label'] ?? null, 'studio' => $studio?->name];
            })->values()->all();

        return ['event' => $event, 'routines' => $routines, 'studios' => $studios,
            'title' => $data['presentation']['title']];
    }
}
