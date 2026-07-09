<?php

namespace Tests\Feature\Downloads;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Services\DownloadUrlSigner;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class DownloadLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_download_links(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/download-links', [
            'keys' => ['folder/file.mp4'],
            'disk' => 's3_competitions',
            'days' => 60,
            'purpose' => 'Competition download links',
            'notes' => 'Optional internal note',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Download links created.')
            ->assertJsonPath('data.0.key', 'folder/file.mp4')
            ->assertJsonPath('data.0.status', DownloadLinkStatus::ACTIVE)
            ->assertJsonStructure([
                'data' => [
                    ['uuid', 'key', 'url', 'expires_at', 'status'],
                ],
            ]);

        $this->assertDatabaseHas('download_links', [
            'storage_disk' => 's3_competitions',
            'storage_key' => 'folder/file.mp4',
            'purpose' => 'Competition download links',
            'notes' => 'Optional internal note',
        ]);
    }

    public function test_guest_cannot_create_download_links(): void
    {
        $response = $this->postJson('/api/download-links', [
            'keys' => ['folder/file.mp4'],
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_duplicate_keys_are_deduplicated_after_normalisation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/download-links', [
            'keys' => ['folder//file.mp4', 'folder/file.mp4'],
            'disk' => 's3_competitions',
        ]);

        $response
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'folder/file.mp4');

        $this->assertDatabaseCount('download_links', 1);
    }

    public function test_unsafe_keys_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/download-links', [
            'keys' => ['../private/file.mp4'],
            'disk' => 's3_competitions',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('keys');

        $this->assertDatabaseCount('download_links', 0);
    }

    public function test_created_link_stores_token_hash_but_not_raw_token(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/download-links', [
            'keys' => ['folder/file.mp4'],
            'disk' => 's3_competitions',
        ]);

        $url = $response->json('data.0.url');
        $token = basename((string) parse_url($url, PHP_URL_PATH));
        $downloadLink = DownloadLink::query()->firstOrFail();

        $this->assertSame(hash('sha256', $token), $downloadLink->token_hash);
        $this->assertNotSame($token, $downloadLink->token_hash);
    }

    public function test_public_valid_token_redirects_and_records_successful_access(): void
    {
        $token = Str::random(64);
        $downloadLink = DownloadLink::factory()->create([
            'token_hash' => hash('sha256', $token),
            'first_opened_at' => null,
            'last_opened_at' => null,
            'download_count' => 0,
        ]);

        $this->mock(DownloadUrlSigner::class, function (MockInterface $mock) use ($downloadLink): void {
            $mock->shouldReceive('signedUrl')
                ->once()
                ->withArgs(fn (DownloadLink $link): bool => $link->is($downloadLink))
                ->andReturn('https://cdn.example.test/folder/file.mp4?signature=test');
        });

        $response = $this->get('/download/'.$token);

        $response
            ->assertRedirect('https://cdn.example.test/folder/file.mp4?signature=test');

        $downloadLink->refresh();

        $this->assertNotNull($downloadLink->first_opened_at);
        $this->assertNotNull($downloadLink->last_opened_at);
        $this->assertSame(1, $downloadLink->download_count);
        $this->assertDatabaseHas('download_accesses', [
            'download_link_id' => $downloadLink->id,
            'was_successful' => true,
            'failure_reason' => null,
        ]);
    }

    public function test_expired_link_does_not_redirect(): void
    {
        $token = Str::random(64);
        $downloadLink = DownloadLink::factory()->expired()->create([
            'token_hash' => hash('sha256', $token),
        ]);

        $response = $this->get('/download/'.$token);

        $response
            ->assertGone()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Download link is no longer available.');

        $this->assertDatabaseHas('download_accesses', [
            'download_link_id' => $downloadLink->id,
            'was_successful' => false,
            'failure_reason' => 'expired',
        ]);
    }

    public function test_revoked_link_does_not_redirect(): void
    {
        $token = Str::random(64);
        $downloadLink = DownloadLink::factory()->revoked()->create([
            'token_hash' => hash('sha256', $token),
        ]);

        $response = $this->get('/download/'.$token);

        $response
            ->assertGone()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Download link is no longer available.');

        $this->assertDatabaseHas('download_accesses', [
            'download_link_id' => $downloadLink->id,
            'was_successful' => false,
            'failure_reason' => 'revoked',
        ]);
    }

    public function test_revoke_endpoint_updates_link_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $downloadLink = DownloadLink::factory()->create([
            'generated_by_user_id' => $user->id,
        ]);

        $response = $this->patchJson('/api/download-links/'.$downloadLink->uuid.'/revoke', [
            'reason' => 'No longer required.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Download link revoked.')
            ->assertJsonPath('data.status', DownloadLinkStatus::REVOKED);

        $downloadLink->refresh();

        $this->assertSame(DownloadLinkStatus::REVOKED, $downloadLink->status);
        $this->assertNotNull($downloadLink->revoked_at);
        $this->assertSame($user->id, $downloadLink->revoked_by_user_id);
        $this->assertSame('No longer required.', $downloadLink->revoke_reason);
    }
}
