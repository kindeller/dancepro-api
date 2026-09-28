<?php

namespace App\Features\StudioBilling\Models;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'studio_id', 'concert_booking_id', 'active_booking_id', 'created_by_user_id', 'status', 'is_test', 'issuer', 'bill_to', 'lines', 'total_cents', 'gst_cents', 'currency', 'source_hash', 'notes', 'due_date', 'issued_at', 'voided_at', 'sent_at', 'sent_by_user_id'])]
class StudioInvoice extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return ['is_test' => 'boolean', 'issuer' => 'array', 'bill_to' => 'array', 'lines' => 'array', 'total_cents' => 'integer', 'gst_cents' => 'integer', 'due_date' => 'date', 'sent_at' => 'datetime', 'issued_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ConcertBooking::class, 'concert_booking_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StudioInvoicePayment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(StudioInvoiceEvent::class)->latest('id');
    }

    public function number(): string
    {
        return (string) (100000 + $this->id);
    }

    public function paidCents(): int
    {
        return $this->relationLoaded('payments') ? (int) $this->payments->where('status', 'succeeded')->sum('amount_cents') : (int) $this->payments()->where('status', 'succeeded')->sum('amount_cents');
    }

    public function balanceCents(): int
    {
        return $this->status === 'void' ? 0 : max(0, $this->total_cents - $this->paidCents());
    }

    public function hasPendingPayment(): bool
    {
        return $this->relationLoaded('payments') ? $this->payments->whereIn('status', ['processing', 'unknown', 'submitted'])->isNotEmpty() : $this->payments()->whereIn('status', ['processing', 'unknown', 'submitted'])->exists();
    }

    public function paymentLabel(): string
    {
        return $this->status !== 'issued' ? ucfirst($this->status) : ($this->hasPendingPayment() ? 'Payment pending' : ($this->balanceCents() === 0 ? 'Paid' : ($this->paidCents() > 0 ? 'Part paid' : ($this->due_date->lt(today()) ? 'Overdue' : 'Unpaid'))));
    }
}
