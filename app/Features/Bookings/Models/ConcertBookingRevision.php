<?php

namespace App\Features\Bookings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concert_booking_id', 'user_id', 'studio_booking_change_request_id', 'before', 'after', 'reason'])]
class ConcertBookingRevision extends Model
{
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
