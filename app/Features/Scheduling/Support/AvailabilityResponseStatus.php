<?php

namespace App\Features\Scheduling\Support;

enum AvailabilityResponseStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
