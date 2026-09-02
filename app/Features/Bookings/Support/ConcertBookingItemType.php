<?php

namespace App\Features\Bookings\Support;

enum ConcertBookingItemType: string
{
    case DressRehearsal = 'dress_rehearsal';
    case Concert = 'concert';
}
