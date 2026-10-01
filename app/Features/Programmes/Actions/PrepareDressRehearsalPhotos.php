<?php

namespace App\Features\Programmes\Actions;

use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Programmes\Models\Programme;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrepareDressRehearsalPhotos
{
    public function execute(SchedulingEvent $event, ?User $actor = null): ?Programme
    {
        return DB::transaction(function () use ($event, $actor): ?Programme {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $item = $event->concertBookingItem;
            if ($event->cancelled_at || ! $item || $item->cancelled_at || $item->approval_status !== 'approved'
                || $item->item_type !== ConcertBookingItemType::DressRehearsal || ! $item->booking->wants_portrait_photography) {
                return null;
            }
            $programme = Programme::firstOrCreate(['scheduling_event_id' => $event->id], ['updated_by_user_id' => $actor?->id]);
            // Keep any existing prepared structure and uploaded identities intact.
            if ($programme->revisions()->exists() || $programme->nodes()->exists()) {
                return $programme;
            }
            $uuid = (string) Str::uuid();
            $programme->nodes()->create(['uuid' => $uuid, 'kind' => 'item', 'sort_order' => 0, 'data' => [
                'uuid' => $uuid, 'parent_uuid' => null, 'kind' => 'item',
                'title' => 'Dress Rehearsal Portraits', 'item_kind' => 'dance',
            ]]);
            app(ConfirmProgramme::class)->execute($programme, $actor, $programme->draft_version);

            return $programme;
        });
    }
}
