<?php

namespace App\Features\Crew\Support;

enum ContractSignatureRecordingMethod: string
{
    case Digital = 'digital';
    case ManualExisting = 'manual_existing';
    case ManualCorrection = 'manual_correction';
}
