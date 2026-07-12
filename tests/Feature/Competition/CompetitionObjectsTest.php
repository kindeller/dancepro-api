<?php

namespace Tests\Feature\Competition;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionObjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_competition_objects(): void
    {
        config([
            'filesystems.disks.s3_competitions.bucket' => 'dance-pro-competitions',
            'filesystems.disks.s3_competitions.region' => 'ap-southeast-2',
        ]);

        Storage::fake('s3_competitions');
        Storage::disk('s3_competitions')->put('competition-a/video-2.mp4', 'video');
        Storage::disk('s3_competitions')->put('competition-a/routines/video-1.mp4', 'video');
        Storage::disk('s3_competitions')->put('competition-b/audio.mp3', 'audio');
        Storage::disk('s3_competitions')->put('root-file.pdf', 'pdf');

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/competitions/objects');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Competition objects returned.')
            ->assertJsonPath('data.disk', 's3_competitions')
            ->assertJsonPath('data.prefix', '')
            ->assertJsonPath('data.directories.0.name', 'competition-a')
            ->assertJsonPath('data.directories.1.name', 'competition-b')
            ->assertJsonPath('data.files.0.name', 'root-file.pdf')
            ->assertJsonStructure([
                'data' => [
                    'disk',
                    'prefix',
                    'breadcrumbs',
                    'pagination' => ['limit', 'next_token', 'has_more'],
                    'directories' => [
                        ['type', 'name', 'prefix'],
                    ],
                    'files' => [
                        ['type', 'name', 'key', 'extension', 'size', 'last_modified', 'console_url'],
                    ],
                ],
            ]);

        $response->assertJsonPath(
            'data.files.0.console_url',
            'https://ap-southeast-2.console.aws.amazon.com/s3/buckets/dance-pro-competitions?region=ap-southeast-2&prefix=root-file.pdf&showversions=false',
        );
    }

    public function test_authenticated_user_can_list_competition_objects_by_prefix(): void
    {
        Storage::fake('s3_competitions');
        Storage::disk('s3_competitions')->put('competition-a/video-2.mp4', 'video');
        Storage::disk('s3_competitions')->put('competition-a/routines/video-1.mp4', 'video');

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/competitions/objects?prefix=competition-a');

        $response
            ->assertOk()
            ->assertJsonPath('data.prefix', 'competition-a')
            ->assertJsonPath('data.breadcrumbs.0.name', 'competition-a')
            ->assertJsonPath('data.directories.0.name', 'routines')
            ->assertJsonPath('data.files.0.name', 'video-2.mp4');
    }

    public function test_guest_cannot_list_competition_objects(): void
    {
        $this->getJson('/api/competitions/objects')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_unsafe_prefixes_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/competitions/objects?prefix=../private')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prefix');
    }

    public function test_admin_can_view_competition_objects_portal(): void
    {
        config([
            'filesystems.disks.s3_competitions.bucket' => 'dance-pro-competitions',
            'filesystems.disks.s3_competitions.region' => 'ap-southeast-2',
        ]);

        Storage::fake('s3_competitions');
        Storage::disk('s3_competitions')->put('competition-a/video-2.mp4', str_repeat('x', 2048));
        Storage::disk('s3_competitions')->put('competition-a/routines/video-1.mp4', 'video');

        $this->actingAs(User::factory()->create())
            ->get('/admin/competitions/objects?prefix=competition-a')
            ->assertOk()
            ->assertSee('Competition Objects')
            ->assertSee('display: none !important', false)
            ->assertSee('data-auto-load-url', false)
            ->assertSee('data-limit="250"', false)
            ->assertSee('competition-objects-card')
            ->assertSee('data-load-state="complete"', false)
            ->assertSee("card.dataset.loadState = 'complete'", false)
            ->assertSee('Loading all objects')
            ->assertSee('Retry loading')
            ->assertSee('competition-objects-loading')
            ->assertDontSee('data-soft-cap', false)
            ->assertDontSee('state.softCap', false)
            ->assertSee('routines')
            ->assertSee('video-2.mp4')
            ->assertSee('2 KB')
            ->assertSee('<time datetime=', false)
            ->assertSee('new Intl.DateTimeFormat(undefined', false)
            ->assertDontSee('Australia/Perth')
            ->assertSee('data-storage-key="competition-a/video-2.mp4"', false)
            ->assertSee('Select competition-a/video-2.mp4 for link creation')
            ->assertSee('dancepro.competition.selected-objects')
            ->assertSee('Open competition-a/video-2.mp4 in the AWS Console')
            ->assertSee('prefix=competition-a%2Fvideo-2.mp4', false);
    }

    public function test_admin_chunk_endpoint_returns_competition_objects_json(): void
    {
        Storage::fake('s3_competitions');
        Storage::disk('s3_competitions')->put('competition-a/video-2.mp4', 'video');
        Storage::disk('s3_competitions')->put('competition-a/routines/video-1.mp4', 'video');

        $this->actingAs(User::factory()->create())
            ->getJson('/admin/competitions/objects/chunk?prefix=competition-a&limit=25')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.prefix', 'competition-a')
            ->assertJsonPath('data.pagination.limit', 25)
            ->assertJsonPath('data.directories.0.name', 'routines')
            ->assertJsonPath('data.files.0.name', 'video-2.mp4');
    }
}
