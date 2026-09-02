<?php

namespace App\Features\Scheduling\Support;

enum SchedulingEventType: string
{
    case Competition = 'competition';
    case Concert = 'concert';
}
