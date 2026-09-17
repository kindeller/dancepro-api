<?php

namespace App\Features\Media\Support;

enum MediaUploadKind: string
{
    case SingleBatch = 'single_batch';
    case Multipart = 'multipart';
}
