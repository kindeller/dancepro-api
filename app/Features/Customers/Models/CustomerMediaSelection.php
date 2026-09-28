<?php

namespace App\Features\Customers\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMediaSelection extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    protected function casts(): array
    {
        return ['is_favourite' => 'boolean', 'in_cart' => 'boolean'];
    }
}
