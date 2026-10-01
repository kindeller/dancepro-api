<?php

namespace Tests\Feature\Media;

use App\Features\Customers\Services\ConcertPhotoPreview;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Services\MediaWatermarks;
use App\Features\Media\Services\StoredGalleryCover;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Services\ProgrammePhotoPreview;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryCoverWatermarkTest extends TestCase
{
    public function test_concert_and_competition_covers_use_the_same_centred_logo_without_changing_originals(): void
    {
        Storage::fake('local');
        $original = $this->photo();
        Storage::disk('local')->put('original.jpg', $original);
        $asset = new MediaAsset(['uuid' => 'concert-photo', 'storage_disk' => 'local', 'storage_key' => 'original.jpg', 'mime_type' => 'image/jpeg']);
        $photo = new ProgrammePhotoFile(['uuid' => 'competition-photo', 'storage_disk' => 'local', 'storage_key' => 'original.jpg', 'sha256' => hash('sha256', $original)]);
        $concert = app(ConcertPhotoPreview::class)->key($asset, 'cover');
        $competition = app(ProgrammePhotoPreview::class)->key($photo, 'cover');
        $this->assertSame(Storage::disk('local')->get($concert), Storage::disk('local')->get($competition));
        $this->assertBranded(Storage::disk('local')->get($concert));
        $this->assertSame($original, Storage::disk('local')->get('original.jpg'));
        $this->assertSame($concert, app(ConcertPhotoPreview::class)->key($asset, 'cover'));

        // Cover generation must not consult either event's tiled sales watermark.
        $this->mock(MediaWatermarks::class, function ($mock) {
            $mock->shouldReceive('galleryCover')->andReturn((new MediaWatermarks)->galleryCover());
            $mock->shouldNotReceive('concert');
            $mock->shouldNotReceive('competition');
        });
        $this->assertSame($concert, app(ConcertPhotoPreview::class)->key($asset, 'cover'));
        $this->assertSame($competition, app(ProgrammePhotoPreview::class)->key($photo, 'cover'));
    }

    public function test_stored_covers_are_cached_separately_by_revision_and_never_return_the_original(): void
    {
        Storage::fake('local');
        Storage::fake('s3_concerts');
        $original = $this->photo();
        Storage::disk('s3_concerts')->put('managed/cover', $original);
        $covers = app(StoredGalleryCover::class);
        $response = $covers->response('s3_concerts', 'managed/cover', 'first');
        ob_start();
        $response->sendContent();
        $this->assertBranded(ob_get_clean());
        $this->assertSame($original, Storage::disk('s3_concerts')->get('managed/cover'));
        $covers->response('s3_concerts', 'managed/cover', 'second');
        $this->assertCount(2, array_filter(Storage::disk('local')->allFiles('gallery-covers'), fn ($key) => str_ends_with($key, '.jpg')));
    }

    private function photo(): string
    {
        $image = imagecreatetruecolor(600, 500);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 40, 60));
        ob_start();
        imagejpeg($image, null, 100);

        return ob_get_clean();
    }

    private function assertBranded(string $bytes): void
    {
        $image = imagecreatefromstring($bytes);
        $this->assertSame(600, imagesx($image));
        $this->assertSame(400, imagesy($image));
        // A clean corner, with a substantial logo only in the centre band.
        $corner = imagecolorsforindex($image, imagecolorat($image, 10, 10));
        $this->assertEqualsWithDelta(20, $corner['red'], 3);
        $changed = 0;
        for ($y = 130; $y < 270; $y += 2) {
            for ($x = 40; $x < 560; $x += 2) {
                $pixel = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $changed += $pixel['red'] > 50 || $pixel['blue'] > 90 ? 1 : 0;
            }
        }
        $this->assertGreaterThan(1000, $changed);
    }
}
