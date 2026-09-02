<?php

namespace Tests\Feature\Deployment;

use App\Features\Deployment\Services\ProductionDependencyCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductionDependencyCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_dependencies_are_checked_without_leaving_a_storage_probe(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('s3_competitions');
        Storage::fake('s3_concerts');
        Storage::fake('s3_concerts_legacy');
        config([
            'operations.filesystem_disk' => 'local',
            'uploads.public_disk' => 'public',
            'cache.default' => 'array',
            'mail.default' => 'log',
            'queue.default' => 'database',
            'concerts.playback.cloudfront.domain' => 'media.example.com',
            'concerts.playback.cloudfront.key_pair_id' => 'test-key-pair',
            'concerts.playback.cloudfront.private_key' => $this->privateKey(),
            'concerts.playback.cloudfront.cookie_domain' => '.example.com',
            'downloads.cloudfront.domain' => null,
            'downloads.cloudfront.key_pair_id' => null,
            'downloads.cloudfront.private_key' => null,
            'downloads.cloudfront.private_key_path' => null,
        ]);

        $checks = app(ProductionDependencyCheck::class)->run();

        $this->assertSame([
            'database',
            'cache',
            'private storage',
            'public upload storage',
            'competition media storage',
            'concert media storage',
            'legacy concert media storage',
            'concert signing',
            'mail transport',
            'queue',
        ], $checks);
        Storage::disk('local')->assertDirectoryEmpty('deployment-health');
        Storage::disk('public')->assertDirectoryEmpty('deployment-health');
    }

    public function test_invalid_concert_signing_key_fails_the_dependency_check(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('s3_competitions');
        Storage::fake('s3_concerts');
        Storage::fake('s3_concerts_legacy');
        config([
            'operations.filesystem_disk' => 'local',
            'uploads.public_disk' => 'public',
            'cache.default' => 'array',
            'concerts.playback.cloudfront.domain' => 'media.example.com',
            'concerts.playback.cloudfront.key_pair_id' => 'test-key-pair',
            'concerts.playback.cloudfront.private_key' => 'not-a-private-key',
            'concerts.playback.cloudfront.cookie_domain' => '.example.com',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Concert CloudFront signing private key is invalid or unreadable.');

        app(ProductionDependencyCheck::class)->run();
    }

    public function test_health_endpoint_has_an_exact_machine_readable_success_response(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'up']);
    }

    private function privateKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));

        return $privateKey;
    }
}
