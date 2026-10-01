<?php

namespace App\Features\Media\Services;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Covers for managed branding and purchased assets, read from their original storage. */
class StoredGalleryCover
{
    public function __construct(private readonly MediaWatermarks $watermarks, private readonly MediaWatermarkRenderer $renderer) {}

    /** The caller must authorise the owner and resolve the stored key, never accept a URL from input. */
    public function response(string $disk, string $sourceKey, ?string $revision): StreamedResponse
    {
        $watermark = $this->watermarks->galleryCover();
        $key = 'gallery-covers/'.hash('sha256', json_encode([$disk, $sourceKey, $revision, $watermark['fingerprint']])).'.jpg';
        $cache = Storage::disk('local');
        $cache->makeDirectory(dirname($key));
        $destination = $cache->path($key);
        $lock = fopen($destination.'.lock', 'c');
        abort_unless(is_resource($lock), 503);
        try {
            abort_unless(flock($lock, LOCK_EX), 503);
            if (! is_file($destination)) {
                $this->render($disk, $sourceKey, $destination, $watermark);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $cache->response($key, 'cover.jpg', ['Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    private function render(string $disk, string $key, string $destination, array $watermark): void
    {
        $stream = Storage::disk($disk)->readStream($key);
        abort_unless(is_resource($stream), 404);
        try {
            $bytes = stream_get_contents($stream, 32 * 1024 * 1024 + 1);
        } finally {
            fclose($stream);
        }
        abort_unless(is_string($bytes) && strlen($bytes) <= 32 * 1024 * 1024, 503);
        $info = @getimagesizefromstring($bytes);
        abort_unless($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            && $info[0] > 0 && $info[1] > 0 && config('programme-media.max_image_pixels') >= $info[0] * $info[1], 503);
        $limit = ini_parse_quantity(ini_get('memory_limit'));
        abort_if($limit > 0 && memory_get_usage(true) + $info[0] * $info[1] * 8 + 16777216 > $limit, 503);
        $source = @imagecreatefromstring($bytes);
        abort_unless($source instanceof GdImage, 503);
        unset($bytes);
        $scale = min(1, 600 / max($info[0], $info[1]));
        $image = imagescale($source, max(1, (int) round($info[0] * $scale)), max(1, (int) round($info[1] * $scale)));
        unset($source);
        abort_unless($image instanceof GdImage, 503);
        $image = $this->renderer->coverCanvas($image);
        $this->renderer->apply($image, $watermark);
        $temp = tempnam(dirname($destination), '.cover-');
        abort_unless($temp !== false, 503);
        try {
            abort_unless(imagejpeg($image, $temp, 88) && chmod($temp, 0600) && rename($temp, $destination), 503);
        } finally {
            unset($image);
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}
