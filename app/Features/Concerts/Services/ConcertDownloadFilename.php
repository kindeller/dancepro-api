<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCollectionStatus;

class ConcertDownloadFilename
{
    public function for(Concert $concert, MediaAsset $asset): string
    {
        $filename = $this->candidate($concert, $asset);
        $matches = MediaAsset::query()
            ->whereHas('collection', fn ($query) => $query
                ->where('concert_id', $concert->id)
                ->where('status', MediaCollectionStatus::Published))
            ->where('status', MediaAssetStatus::Available)
            ->where('is_visible', true)
            ->get()
            ->filter(fn (MediaAsset $other): bool => strcasecmp($this->candidate($concert, $other), $filename) === 0);

        if ($matches->count() < 2) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stem = substr($filename, 0, -strlen($extension) - 1);

        return $stem.'-'.substr($asset->uuid, 0, 8).'.'.$extension;
    }

    private function candidate(Concert $concert, MediaAsset $asset): string
    {
        $name = basename(str_replace('\\', '/', trim((string) $asset->original_filename)));
        if ($name === '' || in_array(strtolower($name), ['video.mp4', 'fallback.mp4'], true)) {
            $name = trim((string) $asset->display_name);
            if ($name === '' || in_array(strtolower($name), ['video', 'fallback'], true)) {
                $name = $concert->name.'-'.substr($asset->uuid, 0, 8);
            }
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $name));
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        if ($extension === '') {
            $name .= '.'.($asset->extension ?: 'mp4');
        }

        return $name;
    }
}
