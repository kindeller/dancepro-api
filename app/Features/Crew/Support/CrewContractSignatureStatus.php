<?php

namespace App\Features\Crew\Support;

enum CrewContractSignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';
    case Voided = 'voided';
}
