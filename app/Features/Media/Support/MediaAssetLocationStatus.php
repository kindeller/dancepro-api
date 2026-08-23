<?php

namespace App\Features\Media\Support;

enum MediaAssetLocationStatus: string
{
    case Active = 'active';
    case Retired = 'retired';
    case Missing = 'missing';
    case Restoring = 'restoring';
}
