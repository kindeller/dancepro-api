<?php

namespace Tests\Feature\Media;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class HlsFinalizationTest extends TestCase
{
    use AuthenticatesApiTokens;
    use RefreshDatabase;

    private const MASTER = "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=2000000,RESOLUTION=1280x720\n720p.m3u8\n";

    private const CHILD = "#EXTM3U\n#EXT-X-TARGETDURATION:6\n#EXTINF:6.0,\nsegment.ts\n#EXT-X-ENDLIST\n";

    public function test_complete_hls_package_becomes_available(): void
    {
        $asset = $this->asset(self::MASTER, self::CHILD);

        $this->finalize($asset)->assertOk()->assertJsonPath('data.status', 'available')->assertJsonPath('data.is_visible', false);
    }

    #[DataProvider('invalidPlaylists')]
    public function test_invalid_hls_package_stays_processing(string $master, string $child): void
    {
        $asset = $this->asset($master, $child);

        $this->finalize($asset)->assertUnprocessable()->assertJsonValidationErrors('expected_outputs');
        $this->assertSame(MediaAssetStatus::Processing, $asset->fresh()->status);
        $this->assertNull($asset->fresh()->verified_at);
    }

    public static function invalidPlaylists(): array
    {
        return [
            'empty master' => ["#EXTM3U\n", self::CHILD],
            'invalid header' => ["not-hls\n", self::CHILD],
            'missing requested rendition' => [str_replace('1280x720', '854x480', self::MASTER), self::CHILD],
            'no bandwidth' => [str_replace('BANDWIDTH=2000000,', '', self::MASTER), self::CHILD],
            'missing variant URI' => [str_replace("720p.m3u8\n", '', self::MASTER), self::CHILD],
            'empty child' => [self::MASTER, "#EXTM3U\n#EXT-X-TARGETDURATION:6\n#EXT-X-ENDLIST\n"],
            'no segment durations' => [self::MASTER, str_replace("#EXTINF:6.0,\n", '', self::CHILD)],
            'unfinished playlist' => [self::MASTER, str_replace("#EXT-X-ENDLIST\n", '', self::CHILD)],
            'duration exceeds target' => [self::MASTER, str_replace('#EXTINF:6.0,', '#EXTINF:7.0,', self::CHILD)],
            'missing segment' => [self::MASTER, str_replace('segment.ts', 'missing.ts', self::CHILD)],
            'external segment' => [self::MASTER, str_replace('segment.ts', 'https://example.test/segment.ts', self::CHILD)],
            'escaped segment' => [self::MASTER, str_replace('segment.ts', '../segment.ts', self::CHILD)],
            'cyclic variant' => [str_replace('720p.m3u8', 'master.m3u8', self::MASTER), self::CHILD],
            'nested master' => [self::MASTER, self::MASTER],
            'fmp4 without map' => [self::MASTER, str_replace('segment.ts', 'segment.m4s', self::CHILD)],
            'external initialization map' => [self::MASTER, str_replace('#EXTINF:', '#EXT-X-MAP:URI="https://example.test/init.mp4"'."\n#EXTINF:", self::CHILD)],
            'encrypted segments unsupported' => [self::MASTER, str_replace('#EXTINF:', '#EXT-X-KEY:METHOD=AES-128,URI="secret.key"'."\n#EXTINF:", self::CHILD)],
        ];
    }

    public function test_all_requested_renditions_must_be_present(): void
    {
        $asset = $this->asset(self::MASTER, self::CHILD, ['original', 'fallback_mp4', 'hls_720p', 'hls_480p']);
        $this->finalize($asset)->assertUnprocessable();

        $prefix = $this->prefix($asset);
        Storage::disk('s3_concerts')->put($prefix.'master.m3u8', self::MASTER."#EXT-X-STREAM-INF:BANDWIDTH=1000000,RESOLUTION=854x480\n480p.m3u8\n");
        Storage::disk('s3_concerts')->put($prefix.'480p.m3u8', self::CHILD);
        $this->finalize($asset)->assertOk();
    }

    public function test_fragmented_mp4_requires_an_existing_initialization_map(): void
    {
        $child = str_replace('segment.ts', 'segment.m4s', self::CHILD);
        $child = str_replace('#EXTINF:', '#EXT-X-MAP:URI="init.mp4"'."\n#EXTINF:", $child);
        $asset = $this->asset(self::MASTER, $child);
        Storage::disk('s3_concerts')->put($this->prefix($asset).'segment.m4s', 'fragment');
        $this->finalize($asset)->assertUnprocessable();

        Storage::disk('s3_concerts')->put($this->prefix($asset).'init.mp4', 'initialization');
        $this->finalize($asset)->assertOk();
    }

    public function test_empty_segments_are_rejected(): void
    {
        $asset = $this->asset(self::MASTER, self::CHILD);
        Storage::disk('s3_concerts')->put($this->prefix($asset).'segment.ts', '');

        $this->finalize($asset)->assertUnprocessable();
    }

    public function test_playlist_and_inventory_limits_fail_closed(): void
    {
        $asset = $this->asset(self::MASTER, self::CHILD);
        config(['media.max_manifest_bytes' => 8]);
        $this->finalize($asset)->assertUnprocessable();

        config(['media.max_manifest_bytes' => 2097152, 'media.max_hls_objects' => 1]);
        $this->finalize($asset)->assertUnprocessable();
    }

    public function test_large_package_uses_paginated_inventory_instead_of_per_segment_head_requests(): void
    {
        $asset = $this->asset(self::MASTER, self::CHILD);
        $prefix = $this->prefix($asset);
        $child = "#EXTM3U\n#EXT-X-TARGETDURATION:6\n";
        $objects = [['key' => $prefix.'master.m3u8', 'size' => strlen(self::MASTER), 'last_modified' => null]];
        for ($i = 0; $i < 1500; $i++) {
            $child .= "#EXTINF:6.0,\nsegment-{$i}.ts\n";
            $objects[] = ['key' => $prefix."segment-{$i}.ts", 'size' => 100, 'last_modified' => null];
        }
        $child .= "#EXT-X-ENDLIST\n";
        $objects[] = ['key' => $prefix.'720p.m3u8', 'size' => strlen($child), 'last_modified' => null];
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($objects, $prefix, $child): void {
            $mock->shouldReceive('head')->times(3)->andReturn(['size' => 1024, 'content_type' => 'video/mp4']);
            $mock->shouldReceive('listObjects')->once()->with('s3_concerts', $prefix, 1000, null)
                ->andReturn(['objects' => array_slice($objects, 0, 1000), 'next_token' => 'page-2']);
            $mock->shouldReceive('listObjects')->once()->with('s3_concerts', $prefix, 1000, 'page-2')
                ->andReturn(['objects' => array_slice($objects, 1000), 'next_token' => null]);
            $mock->shouldReceive('read')->once()->with('s3_concerts', $prefix.'master.m3u8')->andReturn(self::MASTER);
            $mock->shouldReceive('read')->once()->with('s3_concerts', $prefix.'720p.m3u8')->andReturn($child);
        });

        $this->finalize($asset)->assertOk();
    }

    private function asset(string $master, string $child, array $outputs = ['original', 'fallback_mp4', 'hls_720p']): MediaAsset
    {
        Storage::fake('s3_concerts');
        $this->actingAsApiUser(User::factory()->staff()->create(), ['concert-media:upload']);
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Video, 'catalogue_mode' => MediaCatalogueMode::Managed]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => 's3_concerts', 'status' => MediaAssetStatus::Processing, 'verified_at' => null,
            'metadata' => ['ingest' => ['expected_outputs' => $outputs]],
        ]);
        $base = $collection->uuid.'/media/'.$asset->uuid.'/';
        foreach (['original/video.mp4' => 'original', 'stream/fallback.mp4' => 'fallback', 'stream/master.m3u8' => $master, 'stream/720p.m3u8' => $child, 'stream/segment.ts' => 'segment'] as $key => $contents) {
            Storage::disk('s3_concerts')->put($base.$key, $contents);
        }

        return $asset;
    }

    private function prefix(MediaAsset $asset): string
    {
        return $asset->collection->uuid.'/media/'.$asset->uuid.'/stream/';
    }

    private function finalize(MediaAsset $asset): TestResponse
    {
        return $this->postJson("/api/staff/media-assets/{$asset->uuid}/finalize", ['expected_outputs' => $asset->metadata['ingest']['expected_outputs']]);
    }
}
