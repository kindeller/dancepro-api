<?php

namespace App\Features\Bookings\Models;

use App\Features\Bookings\Support\ConcertBookingStatus;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'status', 'submission_fingerprint', 'studio_name', 'contact_name', 'contact_email', 'contact_phone', 'wants_portrait_photography', 'portrait_photography_interest', 'wants_concert_photography', 'wants_concert_videography', 'concert_inclusions', 'multiple_concert_relationship', 'approximate_family_count', 'postal_address', 'previous_video_feedback', 'accepted_requirements', 'internal_review_note', 'reviewed_by_user_id', 'reviewed_at'])]
class ConcertBooking extends Model
{
    use HasPublicUuid;

    public function items(): HasMany
    {
        return $this->hasMany(ConcertBookingItem::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ConcertBookingStatus::class,
            'wants_portrait_photography' => 'boolean',
            'wants_concert_photography' => 'boolean',
            'wants_concert_videography' => 'boolean',
            'concert_inclusions' => 'array',
            'accepted_requirements' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
