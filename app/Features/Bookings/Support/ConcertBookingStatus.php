<?php

namespace App\Features\Bookings\Support;

enum ConcertBookingStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Approved = 'approved';
    case PartialApproval = 'partial_approval';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending approval',
            self::PartialApproval => 'Partial Approval',
            default => ucfirst($this->value),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'overdue',
            self::Received => 'near-due',
            self::Approved, self::PartialApproval => 'complete',
            self::Cancelled, self::Declined => 'not_required',
        };
    }
}
