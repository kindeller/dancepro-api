<?php

namespace App\Features\Concerts\Actions;

use App\Features\Concerts\Support\ConcertMediaPath;
use App\Features\Concerts\Support\ConcertPlaybackFormat;
use App\Features\Concerts\Support\ConcertPlaybackSource;
use App\Features\Media\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveConcertPlaybackSource
{
    public function __construct(private readonly ConcertMediaPath $paths) {}

    public function execute(MediaAsset $asset, bool $hlsDeliveryAvailable): ConcertPlaybackSource
    {
        $disk = $asset->storage_disk;
        $assetPrefix = $this->paths->assetPrefix($asset);

        if ($hlsDeliveryAvailable && Storage::disk($disk)->exists($this->paths->hlsManifest($asset))) {
            return new ConcertPlaybackSource(
                ConcertPlaybackFormat::Hls,
                $disk,
                $this->paths->hlsManifest($asset),
                $assetPrefix,
            );
        }

        $progressiveKeys = array_values(array_unique([
            $this->paths->fallbackVideo($asset),
            $this->paths->canonicalOriginal($asset),
            $asset->storage_key,
        ]));

        foreach ($progressiveKeys as $key) {
            if (filled($key) && Storage::disk($disk)->exists($key)) {
                return new ConcertPlaybackSource(
                    ConcertPlaybackFormat::Progressive,
                    $disk,
                    $key,
                    $assetPrefix,
                );
            }
        }

        throw new NotFoundHttpException('Playable concert media was not found.');
    }
}
