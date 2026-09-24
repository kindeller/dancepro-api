<?php

namespace App\Features\Programmes\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Programme extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = ['draft_version' => 1];

    protected function casts(): array
    {
        return ['draft_version' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProgrammeSource::class, 'programme_source_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(ProgrammeNode::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProgrammeRevision::class);
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(ProgrammeRevision::class)->ofMany('version', 'max');
    }

    public function draftNodes(): array
    {
        return $this->nodes()->where('is_active', true)->orderBy('sort_order')->get()->map(fn ($node) => $node->data)->all();
    }
}
