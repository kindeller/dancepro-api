<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Support\CustomerDancerNameMatcher;
use App\Features\Programmes\Services\CompetitionGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomerDancerRoutines
{
    private ?Collection $studioNames = null;

    public function __construct(
        private readonly CompetitionGallery $gallery,
        private readonly CustomerDancerNameMatcher $nameMatcher,
    ) {}

    public function forEvent(User $user, SchedulingEvent $event): array
    {
        $data = $this->gallery->browse($event, null);
        $dancers = CustomerDancer::where('user_id', $user->id)->orderBy('name')->get()
            ->filter(fn (CustomerDancer $dancer): bool => ! $this->studioNames()->has($this->nameMatcher->canonical($dancer->name)))
            ->values();
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

    private function studioNames(): Collection
    {
        return $this->studioNames ??= Studio::where('status', 'active')->pluck('name')
            ->map(fn (string $name): string => $this->nameMatcher->canonical($name))->flip();
    }

    private function matches(string $title, CustomerDancer $dancer): bool
    {
        $names = collect([$dancer->name, ...($dancer->programme_names ?? [])])
            ->filter(fn ($name) => is_string($name) && trim($name) !== ''
                && ! $this->studioNames()->has($this->nameMatcher->canonical($name)));

        return $names->contains(fn (string $name): bool => $this->nameMatcher->matches($title, $name));
    }
}
