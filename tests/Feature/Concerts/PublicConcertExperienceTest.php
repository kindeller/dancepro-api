<?php

namespace Tests\Feature\Concerts;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaType;
use App\Features\Studios\Models\Studio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicConcertExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_studios_with_available_concerts_are_listed_alphabetically(): void
    {
        $zulu = Studio::factory()->create(['name' => 'Zulu Dance']);
        $alpha = Studio::factory()->create(['name' => 'Alpha Dance']);
        $hidden = Studio::factory()->create(['name' => 'Hidden Dance']);
        Concert::factory()->published()->for($zulu)->create();
        Concert::factory()->published()->for($alpha)->create();
        Concert::factory()->for($hidden)->create();

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Alpha Dance', 'Zulu Dance'])
            ->assertDontSee('Hidden Dance');
    }

    public function test_password_and_student_name_unlock_a_concert_for_the_session(): void
    {
        $concert = Concert::factory()->published()->create(['access_password_hash' => 'dance123']);

        $this->get(route('concerts.show', $concert))->assertOk()->assertSee('Unlock concert');

        $this->post(route('concerts.unlock', $concert), [
            'student_name' => 'Taylor Student',
            'password' => 'wrong',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseHas('concert_accesses', [
            'concert_id' => $concert->id,
            'student_name' => 'Taylor Student',
            'was_successful' => false,
        ]);

        $this->post(route('concerts.unlock', $concert), [
            'student_name' => 'Taylor Student',
            'password' => 'dance123',
        ])->assertRedirect(route('concerts.show', $concert));

        $this->get(route('concerts.show', $concert))
            ->assertOk()
            ->assertDontSee('Unlock concert')
            ->assertSee('Media is not available yet');
    }

    public function test_unavailable_concert_is_not_public(): void
    {
        $concert = Concert::factory()->published()->create(['available_from' => now()->addDay()]);

        $this->get(route('concerts.show', $concert))->assertNotFound();
        $this->getJson('/api/concerts/'.$concert->uuid)->assertNotFound();
    }

    public function test_signed_download_bypasses_password_without_exposing_an_internal_id(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('concerts/performance.mp4', 'video-content');
        $concert = Concert::factory()->published()->create(['access_password_hash' => 'secret']);
        $collection = MediaCollection::factory()->for($concert)->create([
            'status' => MediaCollectionStatus::Published,
            'media_type' => MediaType::Video,
            'storage_disk' => 'local',
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => 'local',
            'storage_key' => 'concerts/performance.mp4',
            'original_filename' => 'performance.mp4',
            'media_type' => MediaType::Video,
        ]);
        $url = URL::temporarySignedRoute('concerts.media.download', now()->addMinute(), [
            'concert' => $concert,
            'asset' => $asset,
            'access' => 1,
        ]);

        $this->get($url)->assertOk()->assertDownload('performance.mp4');
        $this->assertStringContainsString($concert->uuid, $url);
        $this->assertStringContainsString($asset->uuid, $url);
    }

    public function test_playback_prefers_hls_and_returns_cloudfront_cookies_and_progressive_fallback(): void
    {
        Storage::fake('local');
        $this->configureConcertCloudFront();

        [$concert, $collection, $asset] = $this->createVideoAsset();
        $prefix = "{$collection->uuid}/media/{$asset->uuid}";
        Storage::disk('local')->put("{$prefix}/stream/master.m3u8", '#EXTM3U');
        Storage::disk('local')->put("{$prefix}/stream/fallback.mp4", 'stream-video');
        Storage::disk('local')->put($asset->storage_key, 'original-video');

        $response = $this->getJson(route('concerts.media.playback', [$concert, $asset]))
            ->assertOk()
            ->assertJsonPath('data.format', 'hls')
            ->assertJsonPath('data.url', "https://media.dancepro.test/{$prefix}/stream/master.m3u8");

        $cookies = collect($response->headers->getCookies())->keyBy->getName();
        $cookieNames = $cookies->keys()->all();
        $this->assertContains('CloudFront-Policy', $cookieNames);
        $this->assertContains('CloudFront-Signature', $cookieNames);
        $this->assertContains('CloudFront-Key-Pair-Id', $cookieNames);

        $encodedPolicy = $cookies->get('CloudFront-Policy')?->getValue();
        $this->assertIsString($encodedPolicy);
        $policy = json_decode(base64_decode(strtr($encodedPolicy, '-_~', '+=/')), true);
        $this->assertSame(
            "https://media.dancepro.test/{$prefix}/*",
            $policy['Statement'][0]['Resource'],
        );

        $fallbackUrl = $response->json('data.fallback_url');
        $this->assertIsString($fallbackUrl);
        $this->get($fallbackUrl)
            ->assertOk()
            ->assertContent('stream-video');
    }

    public function test_playback_uses_fallback_mp4_when_cloudfront_is_not_configured(): void
    {
        Storage::fake('local');
        config()->set('concerts.playback.cloudfront.domain', null);

        [$concert, $collection, $asset] = $this->createVideoAsset();
        $prefix = "{$collection->uuid}/media/{$asset->uuid}";
        Storage::disk('local')->put("{$prefix}/stream/master.m3u8", '#EXTM3U');
        Storage::disk('local')->put("{$prefix}/stream/fallback.mp4", 'stream-video');
        Storage::disk('local')->put($asset->storage_key, 'original-video');

        $response = $this->getJson(route('concerts.media.playback', [$concert, $asset]))
            ->assertOk()
            ->assertJsonPath('data.format', 'progressive')
            ->assertJsonPath('data.fallback_url', null);

        $this->get($response->json('data.url'))
            ->assertOk()
            ->assertContent('stream-video');
    }

    public function test_playback_falls_back_to_the_recorded_original_storage_key(): void
    {
        Storage::fake('local');
        config()->set('concerts.playback.cloudfront.domain', null);

        [$concert, , $asset] = $this->createVideoAsset();
        Storage::disk('local')->put($asset->storage_key, 'original-video');

        $response = $this->getJson(route('concerts.media.playback', [$concert, $asset]))
            ->assertOk()
            ->assertJsonPath('data.format', 'progressive');

        $this->get($response->json('data.url'))
            ->assertOk()
            ->assertContent('original-video');
    }

    public function test_playback_returns_not_found_when_no_expected_source_exists(): void
    {
        Storage::fake('local');
        config()->set('concerts.playback.cloudfront.domain', null);

        [$concert, , $asset] = $this->createVideoAsset();

        $this->getJson(route('concerts.media.playback', [$concert, $asset]))
            ->assertNotFound();
    }

    /**
     * @return array{Concert, MediaCollection, MediaAsset}
     */
    private function createVideoAsset(): array
    {
        $concert = Concert::factory()->published()->create();
        $collection = MediaCollection::factory()->for($concert)->create([
            'status' => MediaCollectionStatus::Published,
            'media_type' => MediaType::Video,
            'storage_disk' => 'local',
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => 'local',
            'storage_key' => "legacy/{$collection->uuid}/performance.mp4",
            'original_filename' => 'performance.mp4',
            'media_type' => MediaType::Video,
            'mime_type' => 'video/mp4',
        ]);

        return [$concert, $collection, $asset];
    }

    private function configureConcertCloudFront(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));

        config()->set('concerts.playback.cloudfront', [
            'domain' => 'media.dancepro.test',
            'key_pair_id' => 'KTESTKEYPAIR',
            'private_key' => $privateKey,
            'private_key_path' => null,
            'cookie_domain' => '.dancepro.test',
            'cookie_path' => '/',
            'cookie_secure' => true,
            'cookie_same_site' => 'lax',
        ]);
        config()->set('concerts.playback.signed_url_ttl_minutes', 15);
    }
}
