<?php

namespace App\Features\Customers\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestGalleryRegistration extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['email', 'email_hash'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'email' => 'encrypted', 'consent_at' => 'datetime', 'notified_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }
}
