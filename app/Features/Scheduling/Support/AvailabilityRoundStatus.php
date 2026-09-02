<?php

namespace App\Features\Scheduling\Support;

enum AvailabilityRoundStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
}
