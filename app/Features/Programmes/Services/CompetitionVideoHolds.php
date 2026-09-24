<?php

namespace App\Features\Programmes\Services;

use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Services\ReleasePendingCompetitionVideos;
use App\Features\Programmes\Models\CompetitionVideoHold;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompetitionVideoHolds
{
    public function __construct(private readonly CompetitionProgrammeNodes $nodes, private readonly ReleasePendingCompetitionVideos $pendingOrders) {}

    public function set(SchedulingEvent $event, User $actor, string $itemUuid, bool $held): void
    {
        abort_unless($event->event_type->value === 'competition', 422);
        $programme = Programme::where('scheduling_event_id', $event->id)->firstOrFail();
        $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->latest('id')->firstOrFail();
        $item = collect($this->nodes->nodes($plan))->first(fn (array $node) => $node['uuid'] === strtolower($itemUuid)
            && ($node['kind'] ?? null) === 'item' && ($node['merged_into_uuid'] ?? null) === null);
        abort_unless($item, 422, 'Choose a competition routine.');

        $collection = DB::transaction(function () use ($event, $actor, $item, $held): ?MediaCollection {
            $state = CompetitionVideoHold::firstOrNew(['scheduling_event_id' => $event->id, 'item_uuid' => $item['uuid']]);
            if ($state->exists && $state->is_held === $held) {
                return null;
            }
            $state->fill(['is_held' => $held, 'updated_by_user_id' => $actor->id])->save();
            DB::table('competition_video_hold_changes')->insert(['scheduling_event_id' => $event->id,
                'item_uuid' => $item['uuid'], 'is_held' => $held, 'user_id' => $actor->id, 'created_at' => now()]);
            $collection = MediaCollection::where('competition_id', $event->id)->where('media_type', 'video')
                ->where('metadata->item_uuid', $item['uuid'])->where('status', 'published')->first();
            if ($collection) {
                $collection->update(['visibility' => $held ? 'private' : 'customer']);
                $collection->assets()->update(['is_visible' => ! $held]);
            }

            return $collection;
        });
        if (! $held && $collection) {
            $this->pendingOrders->forCollection($event, $collection->refresh());
        }
    }
}
