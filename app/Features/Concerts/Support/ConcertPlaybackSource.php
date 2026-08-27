<?php

namespace App\Features\Concerts\Support;

readonly class ConcertPlaybackSource
{
    public function __construct(
        public ConcertPlaybackFormat $format,
        public string $disk,
        public string $key,
        public string $assetPrefix,
    ) {}

    public function isHls(): bool
    {
        return $this->format === ConcertPlaybackFormat::Hls;
    }
}
