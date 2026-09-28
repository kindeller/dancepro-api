<?php

namespace App\Features\Publicity\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvScreen extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'pairing_code_hash'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    protected function casts(): array
    {
        return ['pairing_expires_at' => 'datetime', 'token_expires_at' => 'datetime', 'paired_at' => 'datetime', 'revoked_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
