<?php

namespace Tests\Feature\Competition;

use App\Features\Auth\Support\TokenAbility;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Media\Models\MediaAsset;
use App\Features\Programmes\Models\CompetitionVideoFile;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class CompetitionObjectsTest extends TestCase
{
    use AuthenticatesApiTokens;
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

        $this->actingAsApiUser(User::factory()->create(), [TokenAbility::CompetitionObjectsRead->value]);

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

        $this->actingAsApiUser(User::factory()->create(), [TokenAbility::CompetitionObjectsRead->value]);

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

    public function test_customer_and_crew_users_cannot_list_competition_objects(): void
    {
        foreach ([User::factory()->customer()->create(), User::factory()->crew()->create()] as $user) {
            $this->actingAsApiUser($user, [TokenAbility::CompetitionObjectsRead->value]);

            $this->getJson('/api/competitions/objects')
                ->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Unauthorised.');
        }
    }

    public function test_inactive_staff_user_cannot_list_competition_objects(): void
    {
        $this->actingAsApiUser(User::factory()->staff()->inactive()->create(), [TokenAbility::CompetitionObjectsRead->value]);

        $this->getJson('/api/competitions/objects')
            ->assertForbidden();
    }

    public function test_active_admin_user_can_list_competition_objects(): void
    {
        Storage::fake('s3_competitions');
        $this->actingAsApiUser(User::factory()->admin()->create(), [TokenAbility::CompetitionObjectsRead->value]);

        $this->getJson('/api/competitions/objects')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_token_without_the_competition_ability_cannot_list_objects(): void
    {
        $this->actingAsApiUser(User::factory()->admin()->create(), [TokenAbility::AccountRead->value]);

        $this->getJson('/api/competitions/objects')->assertForbidden();
    }

    public function test_unsafe_prefixes_are_rejected(): void
    {
        $this->actingAsApiUser(User::factory()->create(), [TokenAbility::CompetitionObjectsRead->value]);

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
            ->assertSee('Competition media')
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
            ->assertSee('placeholder="Filter files"', false)
            ->assertSee('aria-label="Clear file filter"', false)
            ->assertSee('.split(/[^a-z0-9]+/)', false)
            ->assertSee('fileMatches(row.dataset.objectKey, queryTokens)', false)
            ->assertSee('routines')
            ->assertSee('video-2.mp4')
            ->assertSee('2 KB')
            ->assertSee('<time datetime=', false)
            ->assertSee('new Intl.DateTimeFormat(undefined', false)
            ->assertDontSee('Australia/Perth')
            ->assertSee('data-storage-key="competition-a/video-2.mp4"', false)
            ->assertSee('Select competition-a/video-2.mp4 for link creation')
            ->assertSee('dancepro.competition.selected-objects')
            ->assertSee('new Intl.Collator(undefined', false)
            ->assertSee('numeric: true', false)
            ->assertSee('sortObjects();', false)
            ->assertSee('Open competition-a/video-2.mp4 in the AWS Console')
            ->assertSee('prefix=competition-a%2Fvideo-2.mp4', false);
    }

    public function test_admin_links_existing_event_folder_videos_without_changing_stored_objects(): void
    {
        Storage::fake('s3_competitions');
        $contact = CompetitionContact::create([
            'name' => 'Express Dance Challenge', 'code' => 'EDC', 'is_active' => true,
            'organiser_name' => 'Director', 'organiser_email' => 'director@example.test',
            'organiser_phone' => '0400000000',
        ]);
        $event = SchedulingEvent::create([
            'competition_contact_id' => $contact->id, 'name' => 'Existing video event',
            'event_type' => 'competition', 'event_date' => '2026-09-04',
        ]);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $day = (string) Str::uuid();
        $section = (string) Str::uuid();
        $item = (string) Str::uuid();
        $snapshot = ['event_type' => 'competition', 'event_code' => 'EDC926', 'nodes' => [
            ['uuid' => $day, 'kind' => 'day', 'title' => 'Friday', 'date' => '2026-09-04'],
            ['uuid' => $section, 'kind' => 'section', 'number' => '07', 'title' => 'Jazz', 'parent_uuid' => $day],
            ['uuid' => $item, 'kind' => 'item', 'number' => '03', 'title' => 'Jessica', 'parent_uuid' => $section],
        ]];
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        ProgrammeUploadPlan::create(['programme_id' => $programme->id, 'programme_revision_id' => $revision->id,
            'digest' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot]);
        foreach (['EDC926_07_03.mp4', 'EDC926_07_03a.mp4', '926_133_01.mp4'] as $name) {
            Storage::disk('s3_competitions')->put('2026/EDC926/'.$name, 'existing video '.$name);
        }

        $staff = User::factory()->staff()->create();
        $folder = route('admin.competition.objects.index', ['prefix' => '2026/EDC926']);
        $this->actingAs($staff)->get($folder)->assertOk()->assertSee('Existing video event')
            ->assertSee('Link existing videos to gallery')->assertSee('does not move, rename, replace or delete');
        $this->actingAs($staff)->post(route('admin.competition.objects.videos.sync', $event))
            ->assertRedirect($folder)->assertSessionHas('status', fn (string $message): bool => str_contains($message, '2 existing videos linked'));

        $this->assertSame(2, MediaAsset::where('storage_disk', 's3_competitions')->count());
        $this->assertDatabaseHas('media_assets', ['storage_disk' => 's3_competitions',
            'storage_key' => '2026/EDC926/EDC926_07_03.mp4', 'is_visible' => true]);
        $this->assertTrue(app(CustomerCompetitionCart::class)->videoAvailable($event, $item));
        $this->assertSame(0, CompetitionVideoFile::count());
        foreach (['EDC926_07_03.mp4', 'EDC926_07_03a.mp4', '926_133_01.mp4'] as $name) {
            Storage::disk('s3_competitions')->assertExists('2026/EDC926/'.$name);
        }

        $this->actingAs($staff)->post(route('admin.competition.objects.videos.sync', $event))->assertRedirect($folder);
        $this->assertSame(2, MediaAsset::where('storage_disk', 's3_competitions')->count());
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
