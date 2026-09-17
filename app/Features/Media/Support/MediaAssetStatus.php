<?php

namespace App\Features\Media\Support;

enum MediaAssetStatus: string
{
    case Available = 'available';
    case Processing = 'processing';
    case Missing = 'missing';
    case Archived = 'archived';
    case Unavailable = 'unavailable';
}
