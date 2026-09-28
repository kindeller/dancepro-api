<?php

namespace App\Features\Publicity\Services;

use App\Features\Publicity\Models\TvScreen;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Database\Eloquent\Builder;

class ScreenEvents
{
    private function available(): Builder
    {
        return SchedulingEvent::query()
            ->where('event_type', 'competition')->whereNull('cancelled_at')
            ->whereIn('id', fn ($query) => $query->select('scheduling_event_id')->from('tv_slideshows')->where('enabled', true));
    }

    public function catalogue(TvScreen $screen): array
    {
        abort_unless($screen->paired_at, 403);
        $screen->update(['last_seen_at' => now(), 'token_expires_at' => now()->addDays(30)]);

        return ['events' => $this->available()->orderByDesc('event_date')->orderByDesc('id')->get()
            ->map(fn ($event) => ['uuid' => $event->uuid, 'name' => $event->name,
                'date' => $event->event_date?->format('Y-m-d'), 'type' => $event->event_type->value])->all()];
    }

    public function select(TvScreen $screen, string $uuid): void
    {
        abort_unless($screen->paired_at, 403);
        $event = $this->available()->where('uuid', $uuid)->firstOrFail();
        $screen->update(['scheduling_event_id' => $event->id, 'cached_count' => 0, 'cached_revision' => null,
            'last_seen_at' => now(), 'token_expires_at' => now()->addDays(30)]);
    }
}
