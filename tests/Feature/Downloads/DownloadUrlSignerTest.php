<?php

namespace Tests\Feature\Downloads;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Services\DownloadUrlSigner;
use Carbon\CarbonImmutable;
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

    public function test_cloudfront_urls_use_a_valid_canned_policy_signature(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->assertNotFalse($privateKey);
        $this->assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));

        config()->set('downloads.cloudfront.domain', 'downloads.dancepro.test');
        config()->set('downloads.cloudfront.key_pair_id', 'KTESTKEYPAIR');
        config()->set('downloads.cloudfront.private_key', $privateKeyPem);
        config()->set('downloads.signed_url_ttl_minutes', 5);

        $now = CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC');
        $this->travelTo($now);

        $downloadLink = DownloadLink::factory()->create([
            'storage_disk' => 's3_competitions',
            'storage_key' => 'competition/finals/video one.mp4',
            'original_filename' => 'video one.mp4',
        ]);

        $url = app(DownloadUrlSigner::class)->signedUrl($downloadLink);
        $parts = parse_url($url);

        $this->assertIsArray($parts);
        $this->assertSame('https', $parts['scheme']);
        $this->assertSame('downloads.dancepro.test', $parts['host']);
        $this->assertSame('/competition/finals/video%20one.mp4', $parts['path']);

        parse_str($parts['query'], $query);

        $this->assertSame((string) $now->addMinutes(5)->getTimestamp(), $query['Expires']);
        $this->assertSame('KTESTKEYPAIR', $query['Key-Pair-Id']);
        $this->assertArrayHasKey('Signature', $query);
        $this->assertArrayNotHasKey('Policy', $query);

        $resourceUrl = 'https://downloads.dancepro.test/competition/finals/video%20one.mp4';
        $policy = json_encode([
            'Statement' => [[
                'Resource' => $resourceUrl,
                'Condition' => [
                    'DateLessThan' => ['AWS:EpochTime' => $now->addMinutes(5)->getTimestamp()],
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES);
        $signature = base64_decode(strtr($query['Signature'], '-_~', '+=/'), true);
        $publicKey = openssl_pkey_get_details($privateKey)['key'];

        $this->assertNotFalse($signature);
        $this->assertSame(1, openssl_verify($policy, $signature, $publicKey, OPENSSL_ALGO_SHA1));
    }
}
