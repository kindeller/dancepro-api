<?php

namespace App\Features\Customers\Models;

use App\Features\Concerts\Models\Concert;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestGalleryAccess extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['email', 'email_hash'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(GuestGallerySelection::class);
    }

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }
}
