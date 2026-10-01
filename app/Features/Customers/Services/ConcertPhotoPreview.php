<?php

namespace App\Features\Customers\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Services\MediaWatermarkRenderer;
use App\Features\Media\Services\MediaWatermarks;
use GdImage;
use Illuminate\Support\Facades\Storage;

class ConcertPhotoPreview
{
    public function __construct(private readonly MediaWatermarks $watermarks, private readonly MediaWatermarkRenderer $renderer) {}

    public function key(MediaAsset $asset, string $size): string
    {
        abort_unless($asset->storage_disk === 'local' && $asset->mime_type === 'image/jpeg', 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->storage_key), 404);
        $source = $disk->path($asset->storage_key);
        $digest = hash_file('sha256', $source);
        abort_unless($digest !== false, 503);
        $edge = $size === 'cover' ? 600 : ($size === 'thumbnail' ? 480 : 1600);
        $watermark = $size === 'cover' ? $this->watermarks->galleryCover() : $this->watermarks->concert($asset->collection);
        $key = 'concert-previews/'.$asset->uuid.'/'.hash('sha256', 'v3:'.$digest.':'.$edge.':'.$watermark['fingerprint']).'.jpg';
        if ($disk->exists($key)) {
            return $key;
        }
        $disk->makeDirectory(dirname($key));
        $destination = $disk->path($key);
        $lock = fopen($destination.'.lock', 'c');
        abort_unless(is_resource($lock), 503);
        try {
            abort_unless(flock($lock, LOCK_EX), 503);
            if (! is_file($destination)) {
                $this->render($source, $destination, $edge, $watermark, $size === 'cover');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $key;
    }

    private function render(string $source, string $destination, int $edge, array $watermark, bool $cover): void
    {
        $info = @getimagesize($source);
        abort_unless($info && $info[2] === IMAGETYPE_JPEG && $info[0] > 0 && $info[1] > 0
            && config('programme-media.max_image_pixels') >= $info[0] * $info[1], 503);
        $original = @imagecreatefromjpeg($source);
        abort_unless($original instanceof GdImage, 503);
        $scale = min(1, $edge / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $preview = imagecreatetruecolor($width, $height);
        imagecopyresampled($preview, $original, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        unset($original);
        if ($cover) {
            $preview = $this->renderer->coverCanvas($preview);
        }
        $this->renderer->apply($preview, $watermark);
        $temp = tempnam(dirname($destination), '.preview-');
        abort_unless($temp !== false, 503);
        try {
            abort_unless(imagejpeg($preview, $temp, 88) && chmod($temp, 0600) && rename($temp, $destination), 503);
        } finally {
            unset($preview);
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}
