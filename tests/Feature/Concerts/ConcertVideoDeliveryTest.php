<?php

namespace Tests\Feature\Concerts;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertCodeGroup;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConcertVideoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_playback_and_download_use_the_original_key_without_copying_it(): void
    {
        [$concert, $asset] = $this->fixture();
        $before = Storage::disk('s3_concerts_legacy')->allFiles();
        $this->getJson(route('concerts.media.playback', [$concert, $asset]))->assertOk()->assertJsonPath('data.format', 'progressive');
        $this->get(route('concerts.media.stream', [$concert, $asset]))->assertOk();
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertDownload('01 Opening.mp4');
        $this->assertSame($before, Storage::disk('s3_concerts_legacy')->allFiles());
        $this->assertSame('0123456789abcdef0123456789abcdef/01 Opening.mp4', $asset->fresh()->storage_key);
        $asset->update(['is_visible' => false]);
        $this->get(route('concerts.media.stream', [$concert, $asset]))->assertNotFound();
    }

    public function test_group_code_unlock_respects_separate_streaming_and_download_expiry(): void
    {
        [$concert, $asset] = $this->fixture();
        $group = ConcertCodeGroup::create(['studio_id' => $concert->studio_id, 'name' => 'Concert access', 'year' => now()->year,
            'code_hash' => Hash::make('DANCERS'), 'version' => 1, 'released_at' => now()->subMonths(7)]);
        $concert->forceFill(['concert_code_group_id' => $group->id])->save();
        $this->post(route('concerts.unlock', $concert), ['student_name' => 'Dancer', 'password' => 'dancers'])->assertSessionHasNoErrors();
        $this->get(route('concerts.show', $concert))->assertOk()->assertSee('Streaming access has ended')->assertSee('Download manager')->assertSee('data-concert-download', false)->assertDontSee('concert-player"', false);
        $this->get(route('concerts.media.stream', [$concert, $asset]))->assertForbidden();
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertDownload();
        $group->update(['released_at' => now()->subMonths(13)]);
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertForbidden();
    }

    public function test_private_original_delivery_signs_the_recorded_disk_and_key(): void
    {
        [$concert, $asset] = $this->fixture();
        config()->set('filesystems.disks.s3_concerts_legacy.driver', 's3');
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->with($asset->storage_key)->times(2)->andReturnTrue();
        $disk->shouldReceive('temporaryUrl')->withArgs(fn ($key, $expiry, $options) => $key === $asset->storage_key && $expiry->isFuture() && ($options['ResponseContentType'] ?? null) === 'video/mp4'
        )->once()->andReturn('https://legacy.example.test/signed-stream');
        $disk->shouldReceive('temporaryUrl')->withArgs(fn ($key, $expiry, $options) => $key === $asset->storage_key && $expiry->isFuture() && str_starts_with($options['ResponseContentDisposition'] ?? '', 'attachment;')
        )->once()->andReturn('https://legacy.example.test/signed-download');
        Storage::set('s3_concerts_legacy', $disk);
        $this->getJson(route('concerts.media.playback', [$concert, $asset]))->assertOk()->assertJsonPath('data.url', 'https://legacy.example.test/signed-stream');
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertRedirect('https://legacy.example.test/signed-download');
    }

    public function test_original_downloads_require_access_and_cannot_cross_concerts(): void
    {
        [$concert, $asset] = $this->fixture();
        $concert->update(['access_password_hash' => 'private-code']);
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertForbidden();
        $this->post(route('concerts.unlock', $concert), ['student_name' => 'Dancer', 'password' => 'private-code'])->assertSessionHasNoErrors();
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertDownload();
        $other = Concert::factory()->published()->create();
        $this->get(route('concerts.media.download', [$other, $asset]))->assertNotFound();
        $concert->update(['is_enabled' => false]);
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertNotFound();
    }

    private function fixture(): array
    {
        Storage::fake('local');
        Storage::fake('s3_concerts');
        Storage::fake('s3_concerts_legacy');
        config()->set('filesystems.disks.s3_concerts_legacy.driver', 'local');
        $concert = Concert::factory()->published()->create();
        $collection = MediaCollection::factory()->for($concert)->create(['media_type' => 'video', 'status' => 'published', 'storage_disk' => 's3_concerts_legacy']);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create(['media_type' => 'video', 'extension' => 'mp4',
            'storage_disk' => 's3_concerts_legacy', 'storage_key' => '0123456789abcdef0123456789abcdef/01 Opening.mp4', 'original_filename' => '01 Opening.mp4',
            'size_bytes' => strlen('video bytes')]);
        Storage::disk('s3_concerts_legacy')->put($asset->storage_key, 'video bytes');

        return [$concert, $asset];
    }
}
