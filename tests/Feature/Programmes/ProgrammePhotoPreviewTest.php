<?php

namespace Tests\Feature\Programmes;

use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Services\ProgrammePhotoPreview;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProgrammePhotoPreviewTest extends TestCase
{
    private function photo(int $width, int $height, int $orientation = 1): ProgrammePhotoFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 80, 120));
        imagefilledrectangle($image, 0, 0, (int) ($width / 5), (int) ($height / 5), imagecolorallocate($image, 230, 20, 20));
        ob_start();
        imagejpeg($image, null, 95);
        $bytes = ob_get_clean();
        unset($image);
        // A minimal little-endian TIFF orientation tag, plus metadata that must not reach previews.
        $exif = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVVV', 0x112, 3, 1, $orientation, 0);
        $comment = 'private-camera-metadata';
        $bytes = substr($bytes, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif
            ."\xff\xfe".pack('n', strlen($comment) + 2).$comment.substr($bytes, 2);
        $uuid = (string) Str::uuid();
        $key = 'originals/'.$uuid.'.jpg';
        Storage::disk('local')->put($key, $bytes);

        return new ProgrammePhotoFile(['uuid' => $uuid, 'storage_disk' => 'local', 'storage_key' => $key, 'sha256' => hash('sha256', $bytes)]);
    }

    public function test_previews_are_resized_watermarked_cached_and_do_not_change_originals(): void
    {
        Storage::fake('local');
        $file = $this->photo(2400, 1600);
        $service = app(ProgrammePhotoPreview::class);
        $disk = Storage::disk('local');
        $original = $disk->get($file->storage_key);
        $key = $service->key($file, 'preview');
        $bytes = $disk->get($key);
        $this->assertSame([1600, 1067], array_slice(getimagesizefromstring($bytes), 0, 2));
        $this->assertStringNotContainsString('private-camera-metadata', $bytes);
        $preview = imagecreatefromstring($bytes);
        $plain = imagecolorsforindex($preview, imagecolorat($preview, 1590, 1060));
        $this->assertEqualsWithDelta(40, $plain['red'], 5);
        $changed = 0;
        for ($x = 600; $x < 1000; $x += 10) {
            for ($y = 400; $y < 700; $y += 10) {
                $rgb = imagecolorsforindex($preview, imagecolorat($preview, $x, $y));
                if (abs($rgb['red'] - 40) > 15 || abs($rgb['green'] - 80) > 15) {
                    $changed++;
                }
            }
        }
        $this->assertGreaterThan(100, $changed, 'The centre must contain the baked-in watermark.');
        touch($disk->path($key), 1000000000);
        $this->assertSame($key, $service->key($file, 'preview'));
        clearstatcache();
        $this->assertSame(1000000000, filemtime($disk->path($key)), 'Cached previews must not be regenerated.');
        $thumb = $disk->get($service->key($file, 'thumbnail'));
        $this->assertSame([480, 320], array_slice(getimagesizefromstring($thumb), 0, 2));
        $this->assertLessThan(strlen($bytes), strlen($thumb));
        $this->assertSame($original, $disk->get($file->storage_key));
    }

    public function test_orientation_is_applied_before_metadata_is_removed_and_small_photos_are_not_enlarged(): void
    {
        Storage::fake('local');
        $service = app(ProgrammePhotoPreview::class);
        foreach ([1 => [240, 160], 2 => [240, 160], 3 => [240, 160], 4 => [240, 160],
            5 => [160, 240], 6 => [160, 240], 7 => [160, 240], 8 => [160, 240]] as $orientation => $dimensions) {
            $file = $this->photo(240, 160, $orientation);
            $bytes = Storage::disk('local')->get($service->key($file, 'preview'));
            $this->assertSame($dimensions, array_slice(getimagesizefromstring($bytes), 0, 2));
            $this->assertStringNotContainsString('Exif', $bytes);
            [$x, $y] = [1 => [10, 10], 2 => [230, 10], 3 => [230, 150], 4 => [10, 150],
                5 => [10, 10], 6 => [150, 10], 7 => [150, 230], 8 => [10, 230]][$orientation];
            $image = imagecreatefromstring($bytes);
            $corner = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            $this->assertGreaterThan(200, $corner['red']);
            $this->assertLessThan(50, $corner['green']);
        }
    }

    public function test_missing_or_changed_original_never_falls_back_to_original_bytes(): void
    {
        Storage::fake('local');
        $file = $this->photo(100, 100);
        Storage::disk('local')->put($file->storage_key, 'damaged');
        try {
            app(ProgrammePhotoPreview::class)->key($file, 'preview');
            $this->fail('Damaged originals must not be served.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
        $this->assertSame([], array_filter(Storage::disk('local')->allFiles('programme-previews'), fn ($path) => str_ends_with($path, '.jpg')));
    }
}
