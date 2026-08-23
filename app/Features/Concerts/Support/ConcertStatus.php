<?php

namespace App\Features\Concerts\Support;

enum ConcertStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Published = 'published';
    case Archived = 'archived';
    case Restoring = 'restoring';
    case Unavailable = 'unavailable';
}
