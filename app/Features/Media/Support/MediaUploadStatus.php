<?php

namespace App\Features\Media\Support;

enum MediaUploadStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Aborted = 'aborted';
    case Failed = 'failed';
}
