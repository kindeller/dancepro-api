<?php

namespace App\Features\Programmes\Services;

use App\Features\Media\Services\MediaWatermarkRenderer;
use App\Features\Media\Services\MediaWatermarks;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Scheduling\Models\SchedulingEvent;
use GdImage;
use Illuminate\Support\Facades\Storage;

class ProgrammePhotoPreview
{
    public function __construct(private readonly MediaWatermarks $watermarks, private readonly MediaWatermarkRenderer $renderer) {}

    /** Produce a private derivative; never return the original when processing fails. */
    public function key(ProgrammePhotoFile $file, string $size, ?SchedulingEvent $event = null): string
    {
        $edge = $size === 'thumbnail' ? 480 : 1600;
        $watermark = $this->watermarks->competition($event);

        $version = hash('sha256', 'v2:'.$file->sha256.':'.$watermark['fingerprint'].':'.$edge.':88');

        return $this->derivative($file, $edge, $watermark, 'programme-previews', $version);
    }

    /** Only private publicity endpoints may expose this derivative, never customer galleries. */
    public function publicityKey(ProgrammePhotoFile $file): string
    {
        return $this->derivative($file, 1920, null, 'publicity/photos', hash('sha256', 'unwatermarked-v1:'.$file->sha256.':1920:88'));
    }

    private function derivative(ProgrammePhotoFile $file, int $edge, ?array $watermark, string $prefix, string $version): string
    {
        $disk = Storage::disk('local');
        abort_unless($file->storage_disk === 'local' && $disk->exists($file->storage_key), 404);
        $key = $prefix.'/'.$file->uuid.'/'.$version.'.jpg';
        if ($disk->exists($key)) {
            return $key;
        }
        $disk->makeDirectory(dirname($key));
        $path = $disk->path($key);
        $lock = fopen($path.'.lock', 'c');
        abort_unless(is_resource($lock), 503, 'Unable to prepare photo preview.');
        try {
            abort_unless(flock($lock, LOCK_EX), 503, 'Unable to lock photo preview.');
            if (! is_file($path)) {
                $this->render($disk->path($file->storage_key), $watermark, $path, $edge, $file->sha256);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $key;
    }

    private function render(string $source, ?array $watermark, string $destination, int $edge, string $sha256): void
    {
        $info = @getimagesize($source);
        abort_unless($info && $info[2] === IMAGETYPE_JPEG && $info[0] > 0 && $info[1] > 0
            && config('programme-media.max_image_pixels') >= $info[0] * $info[1], 503, 'Photo preview cannot be generated.');
        $limit = ini_parse_quantity(ini_get('memory_limit'));
        $needed = ($info[0] * $info[1] + $edge * $edge * 3) * 8 + 16777216;
        abort_if($limit > 0 && memory_get_usage(true) + $needed > $limit, 503, 'Photo preview exceeds the processing memory limit.');
        abort_unless(hash_equals($sha256, hash_file('sha256', $source)), 503, 'Stored photo verification failed.');
        $original = @imagecreatefromjpeg($source);
        abort_unless($original instanceof GdImage, 503, 'Photo preview cannot be decoded.');
        $scale = min(1, $edge / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $preview = imagecreatetruecolor($width, $height);
        imagecopyresampled($preview, $original, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        unset($original);
        $exif = function_exists('exif_read_data') ? @exif_read_data($source, 'IFD0') : false;
        $preview = $this->orient($preview, (int) ($exif['Orientation'] ?? 1));
        $width = imagesx($preview);
        $height = imagesy($preview);
        if ($watermark !== null) {
            $this->renderer->apply($preview, $watermark);
        }
        // Publish only the finished JPEG, so simultaneous requests cannot read a partial file.
        $temp = tempnam(dirname($destination), '.preview-');
        abort_unless($temp !== false, 503, 'Unable to save photo preview.');
        try {
            abort_unless(imagejpeg($preview, $temp, 88) && chmod($temp, 0600) && rename($temp, $destination), 503, 'Unable to save photo preview.');
        } finally {
            unset($preview);
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) {
            3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }
}
