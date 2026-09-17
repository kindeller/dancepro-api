<?php

namespace App\Features\Concerts\Support;

enum ConcertPlaybackFormat: string
{
    case Hls = 'hls';
    case Progressive = 'progressive';
}
