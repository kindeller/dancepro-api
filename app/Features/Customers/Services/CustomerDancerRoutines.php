<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Programmes\Services\CompetitionGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class CustomerDancerRoutines
{
    public function __construct(private readonly CompetitionGallery $gallery) {}

    public function forEvent(User $user, SchedulingEvent $event): array
    {
        $data = $this->gallery->browse($event, null);
        $dancers = CustomerDancer::where('user_id', $user->id)->orderBy('name')->get();
        $nodes = $data['nodes'];
        $ready = $data['sectionReadiness'];
        $routines = $nodes->where('kind', 'item')->filter(function (array $item) use ($ready, $dancers): bool {
            return ($ready[$item['parent_uuid']]['ready'] ?? false)
                && $dancers->contains(fn (CustomerDancer $dancer) => $this->matches($item['title'] ?? '', $dancer));
        })->map(function (array $item) use ($nodes, $dancers): array {
            $section = $nodes->get($item['parent_uuid']);
            $day = $nodes->get($section['parent_uuid'] ?? '');
            $matched = $dancers->filter(fn (CustomerDancer $dancer) => $this->matches($item['title'] ?? '', $dancer))->pluck('name')->values()->all();

            return ['uuid' => $item['uuid'], 'label' => $item['label'], 'section' => $section['label'] ?? null,
                'day' => $day['label'] ?? null, 'dancers' => $matched];
        })->values()->all();

        return ['event' => $event, 'routines' => $routines, 'dancers' => $dancers,
            'title' => $data['presentation']['title']];
    }

    private function matches(string $title, CustomerDancer $dancer): bool
    {
        $names = collect([$dancer->name, ...($dancer->programme_names ?? [])])->filter(fn ($name) => is_string($name) && trim($name) !== '');

        return $names->contains(function (string $name) use ($title): bool {
            $parts = preg_split('/\s+/u', trim($name));
            $phrase = implode('\\s+', array_map(fn ($part) => preg_quote($part, '/'), $parts));

            return preg_match('/(?<![\pL\pN])'.$phrase.'(?![\pL\pN])/iu', $title) === 1;
        });
    }
}
