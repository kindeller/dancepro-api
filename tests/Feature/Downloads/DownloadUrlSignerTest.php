<?php

namespace Tests\Feature\Downloads;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Services\DownloadUrlSigner;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DownloadUrlSignerTest extends TestCase
{
    use RefreshDatabase;

    public function test_s3_temporary_urls_are_signed_as_attachments(): void
    {
        config()->set('downloads.cloudfront.domain', null);

        $downloadLink = DownloadLink::factory()->create([
            'storage_disk' => 's3_competitions',
            'storage_key' => 'competition/video.mp4',
            'original_filename' => 'video.mp4',
        ]);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->withArgs(function (string $path, mixed $expiration, array $options): bool {
                return $path === 'competition/video.mp4'
                    && $options['ResponseContentDisposition'] === 'attachment; filename="video.mp4"; filename*=UTF-8\'\'video.mp4';
            })
            ->andReturn('https://s3.example.test/competition/video.mp4?signature=test');

        Storage::shouldReceive('disk')
            ->once()
            ->with('s3_competitions')
            ->andReturn($disk);

        $url = app(DownloadUrlSigner::class)->signedUrl($downloadLink);

        $this->assertSame('https://s3.example.test/competition/video.mp4?signature=test', $url);
    }
}
