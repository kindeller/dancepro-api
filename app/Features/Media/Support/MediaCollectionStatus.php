<?php

namespace App\Features\Media\Support;

enum MediaCollectionStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Published = 'published';
    case Archived = 'archived';
    case Unavailable = 'unavailable';
}
