<?php

namespace App\Features\Bookings\Models;

use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'concert_booking_id', 'event_type_definition_id', 'item_type', 'title', 'venue_name', 'venue_address', 'venue_id', 'event_date', 'starts_at', 'finishes_at', 'approval_status', 'approved_by_user_id', 'approved_at', 'scheduling_event_id'])]
class ConcertBookingItem extends Model
{
    use HasPublicUuid;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ConcertBooking::class, 'concert_booking_id');
    }

    public function schedulingEvent(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function eventTypeDefinition(): BelongsTo
    {
        return $this->belongsTo(EventTypeDefinition::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    protected function casts(): array
    {
        return ['item_type' => ConcertBookingItemType::class, 'event_date' => 'date', 'approved_at' => 'datetime'];
    }
}
