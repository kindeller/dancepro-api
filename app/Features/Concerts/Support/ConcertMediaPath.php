<?php

namespace App\Features\Concerts\Support;

use App\Features\Media\Models\MediaAsset;

class ConcertMediaPath
{
    public function assetPrefix(MediaAsset $asset): string
    {
        $asset->loadMissing('collection');

        return sprintf(
            '%s/media/%s',
            trim($asset->collection->uuid, '/'),
            trim($asset->uuid, '/'),
        );
    }

    public function hlsManifest(MediaAsset $asset): string
    {
        return $this->assetPrefix($asset).'/stream/master.m3u8';
    }

    public function fallbackVideo(MediaAsset $asset): string
    {
        return $this->assetPrefix($asset).'/stream/fallback.mp4';
    }

    public function canonicalOriginal(MediaAsset $asset): string
    {
        return $this->assetPrefix($asset).'/original/video.mp4';
    }
}
