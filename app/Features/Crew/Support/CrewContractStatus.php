<?php

namespace App\Features\Crew\Support;

enum CrewContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';
}
