<?php

namespace App\Features\Media\Support;

enum MediaType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Mixed = 'mixed';
    case Download = 'download';
    case Thumbnail = 'thumbnail';
}
