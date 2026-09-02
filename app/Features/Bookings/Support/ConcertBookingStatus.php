<?php

namespace App\Features\Bookings\Support;

enum ConcertBookingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}
