<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Support\CustomerDancerNameMatcher;
use App\Features\Programmes\Models\CompetitionRoutineStudio;
use App\Features\Programmes\Services\CompetitionGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class CustomerStudioRoutines
{
    public function __construct(
        private readonly CompetitionGallery $gallery,
        private readonly CustomerDancerNameMatcher $nameMatcher,
    ) {}

    public function forEvent(User $user, SchedulingEvent $event): array
    {
        $data = $this->gallery->browse($event, null);
        $studios = CustomerStudio::where('user_id', $user->id)
            ->whereNotNull('access_verified_at')->whereHas('studio', fn ($query) => $query->where('status', 'active'))
            ->with('studio')->get()->pluck('studio')->sortBy('name')->values();
        $assignments = CompetitionRoutineStudio::where('scheduling_event_id', $event->id)->get()->keyBy('item_uuid');
        $customerStudioIds = $studios->pluck('id')->flip();
        $studiosByLongestName = $studios->sortByDesc(fn ($studio) => mb_strlen($this->nameMatcher->canonical($studio->name)))->values();
        $nodes = $data['nodes'];
        $ready = $data['sectionReadiness'];
        $routines = $nodes->where('kind', 'item')->map(function (array $item) use ($assignments, $customerStudioIds, $studiosByLongestName, $nodes): ?array {
            $assignment = $assignments->get($item['uuid']);
            if ($assignment) {
                $studio = $customerStudioIds->has($assignment->studio_id)
                    ? $studiosByLongestName->firstWhere('id', $assignment->studio_id)
                    : null;
            } else {
                $studio = $studiosByLongestName->first(
                    fn ($candidate) => $this->nameMatcher->startsWith($item['title'] ?? '', $candidate->name),
                );
            }

            $section = $nodes->get($item['parent_uuid'] ?? '');

            return $studio && $this->isStudioRoutine($item, $section, $studio->name)
                ? ['item' => $item, 'studio' => $studio]
                : null;
        })->filter()->filter(fn (array $match) => $ready[$match['item']['parent_uuid']]['ready'] ?? false)
            ->map(function (array $match) use ($nodes): array {
                $item = $match['item'];
                $section = $nodes->get($item['parent_uuid']);
                $day = $nodes->get($section['parent_uuid'] ?? '');

                return ['uuid' => $item['uuid'], 'label' => $item['label'], 'section' => $section['label'] ?? null,
                    'day' => $day['label'] ?? null, 'studio' => $match['studio']->name];
            })->values()->all();

        return ['event' => $event, 'routines' => $routines, 'studios' => $studios,
            'title' => $data['presentation']['title']];
    }

    private function isStudioRoutine(array $item, ?array $section, string $studioName): bool
    {
        if ($this->nameMatcher->startsWith($item['title'] ?? '', $studioName)) {
            return true;
        }

        $sectionName = $this->nameMatcher->canonical($section['title'] ?? $section['label'] ?? '');

        return preg_match('/\b(group|troupe|production|ensemble|team)\b/u', $sectionName) === 1;
    }
}
