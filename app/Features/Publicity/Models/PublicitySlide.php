<?php

namespace App\Features\Publicity\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PublicitySlide extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function scopeForEvent(Builder $query, SchedulingEvent $event): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $standard) => $standard->whereNull('scheduling_event_id')->where('audience', $event->event_type->value))
            ->orWhere('scheduling_event_id', $event->id));
    }

    protected function casts(): array
    {
        return ['play_sound' => 'boolean', 'video_bytes' => 'integer', 'video_seconds' => 'integer', 'enabled' => 'boolean', 'duration_seconds' => 'integer', 'sort_order' => 'integer'];
    }
}
