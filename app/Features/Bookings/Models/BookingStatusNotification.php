<?php

namespace App\Features\Bookings\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concert_booking_id', 'created_by_user_id', 'booking_status', 'reason', 'context_hash', 'recipient_email', 'subject', 'body', 'delivery_status', 'sent_by_user_id', 'sent_at', 'studio_message_id'])]
class BookingStatusNotification extends Model
{
    use HasPublicUuid;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ConcertBooking::class, 'concert_booking_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
