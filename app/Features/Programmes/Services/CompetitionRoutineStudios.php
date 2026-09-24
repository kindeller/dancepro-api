<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\CompetitionRoutineStudio;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompetitionRoutineStudios
{
    public function __construct(private readonly CompetitionGallery $gallery) {}

    public function editor(SchedulingEvent $event, array $browse): array
    {
        if (($browse['selected']['kind'] ?? null) !== 'item') {
            return ['routineStudio' => null, 'routineStudios' => collect()];
        }

        return ['routineStudio' => CompetitionRoutineStudio::with('studio')->where('scheduling_event_id', $event->id)
            ->where('item_uuid', $browse['selected']['uuid'])->first(),
            'routineStudios' => Studio::where('status', 'active')->orderBy('name')->get()];
    }

    public function save(SchedulingEvent $event, User $actor, array $input): void
    {
        DB::transaction(function () use ($event, $actor, $input): void {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($event->event_type->value === 'competition' && $event->cancelled_at === null, 409);
            $browse = $this->gallery->browse($event, null);
            $item = $browse['nodes']->get($input['item_uuid']);
            abort_unless($item && $item['kind'] === 'item' && ! $browse['stale'], 409, 'The programme changed. Reload the gallery before assigning a studio.');
            $current = CompetitionRoutineStudio::with('studio')->where('scheduling_event_id', $event->id)
                ->where('item_uuid', $item['uuid'])->first();
            abort_unless($current?->studio?->uuid === ($input['expected_studio_uuid'] ?? null), 409, 'The studio assignment changed. Reload this album.');
            $studio = isset($input['studio_uuid']) ? Studio::where('uuid', $input['studio_uuid'])->where('status', 'active')->firstOrFail() : null;
            if ($current?->studio_id === $studio?->id) {
                return;
            }
            DB::table('competition_routine_studio_changes')->insert(['scheduling_event_id' => $event->id,
                'item_uuid' => $item['uuid'], 'before_studio_id' => $current?->studio_id, 'after_studio_id' => $studio?->id,
                'actor_user_id' => $actor->id, 'created_at' => now()]);
            if ($current) {
                $current->update(['studio_id' => $studio?->id, 'updated_by_user_id' => $actor->id]);
            } else {
                CompetitionRoutineStudio::create(['scheduling_event_id' => $event->id, 'item_uuid' => $item['uuid'],
                    'studio_id' => $studio?->id, 'updated_by_user_id' => $actor->id]);
            }
        });
    }
}
