<?php

namespace Tests\Feature\Programmes;

use App\Features\Programmes\Models\ConcertTitleCardPackage;
use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeRevision;
use App\Features\Programmes\Services\ConcertTitlePackages;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;
use ZipArchive;

class ConcertTitlePackageTest extends TestCase
{
    use AuthenticatesApiTokens, RefreshDatabase;

    private User $staff;

    private SchedulingEvent $event;

    private ProgrammeRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();
        config(['operations.filesystem_disk' => 'local', 'operations.malware_scan.enabled' => false]);
        Storage::fake('local');
        $this->staff = User::factory()->staff()->create();
        $this->event = SchedulingEvent::create(['name' => 'Concert', 'event_type' => 'concert', 'event_date' => '2026-10-10']);
        $programme = Programme::create(['scheduling_event_id' => $this->event->id]);
        $this->revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'confirmed_by_user_id' => $this->staff->id,
            'snapshot' => ['nodes' => [['uuid' => fake()->uuid(), 'kind' => 'item', 'folder_number' => '01', 'title' => 'Opening']]],
        ])->fresh();
    }

    public function test_ready_publishes_private_jpegs_and_records_the_publisher(): void
    {
        $this->publish()->assertOk();
        $package = ConcertTitleCardPackage::firstOrFail();
        Storage::disk('local')->assertExists($package->path);
        $task = ConcertTrackerTask::where('task_key', 'video_titles')->firstOrFail();
        $this->assertSame('complete', $task->status);
        $this->assertSame($this->staff->id, $task->completed_by_user_id);
        $this->assertSame('complete', app(ConcertTracker::class)->detail($this->event->fresh(), 'video_titles')['status']['key']);
        $this->actingAsApiUser($this->staff, ['programmes:read']);
        $meta = $this->getJson($this->api())->assertOk()->assertJsonPath('data.ready', true)->assertJsonPath('data.revision_uuid', $this->revision->uuid)->json('data');
        $download = $this->get($meta['download_url'])->assertOk()->assertHeader('Content-Type', 'application/zip')->streamedContent();
        $this->assertSame($package->sha256, hash('sha256', $download));
        $this->assertSame($package->size_bytes, strlen($download));
        $other = SchedulingEvent::create(['name' => 'Other', 'event_type' => 'concert', 'event_date' => '2026-10-11']);
        $this->getJson(str_replace($this->event->uuid, $other->uuid, $meta['download_url']))->assertNotFound();
    }

    public function test_changed_templates_and_programmes_withdraw_old_downloads(): void
    {
        $this->publish()->assertOk();
        $package = ConcertTitleCardPackage::firstOrFail();
        ConcertTitleCardTemplate::firstOrFail()->update(['background_color' => '#112233']);
        $this->assertNull(app(ConcertTitlePackages::class)->downloadable($this->event->fresh()));
        $this->assertSame('in_progress', app(ConcertTracker::class)->detail($this->event->fresh(), 'video_titles')['status']['key']);
        $this->publish()->assertOk();
        Storage::disk('local')->assertExists($package->path);
        $this->revision->update(['version' => 2]);
        $programme = Programme::findOrFail($this->revision->programme_id);
        $programme->revisions()->create(['version' => 3, 'confirmed_at' => now(), 'snapshot' => $this->revision->snapshot]);
        $this->assertNull(app(ConcertTitlePackages::class)->downloadable($this->event->fresh()));
        $this->publish()->assertConflict();
    }

    public function test_republishing_updates_staff_and_date_and_manual_reopening_hides_download(): void
    {
        $this->publish()->assertOk();
        $this->travel(1)->days();
        $this->staff = User::factory()->staff()->create();
        $this->publish()->assertOk();
        $task = ConcertTrackerTask::where('task_key', 'video_titles')->firstOrFail();
        $this->assertSame($this->staff->id, $task->completed_by_user_id);
        $this->assertTrue($task->completed_at->isToday());
        $this->assertDatabaseCount('concert_title_card_packages', 2);
        $this->patchJson(route('admin.concert-tracker.source-bar.update', [$this->event, 'video_titles']), ['panel' => 'status', 'status_override' => 'in_progress'])->assertOk();
        $this->assertNull(app(ConcertTitlePackages::class)->downloadable($this->event->fresh()));
    }

    public function test_malformed_archives_do_not_mark_titles_ready(): void
    {
        foreach (['../Opening.jpg', 'not-an-image.jpg'] as $name) {
            $this->publish($this->archive($name, 'invalid'))->assertUnprocessable();
        }
        $this->assertDatabaseCount('concert_title_card_packages', 0);
        $this->assertDatabaseCount('concert_tracker_tasks', 0);
        $this->publish(null, ['content_fingerprint' => str_repeat('0', 64)])->assertConflict();
    }

    public function test_downloads_require_staff_permission_and_programme_token_ability(): void
    {
        $this->getJson($this->api())->assertUnauthorized();
        $this->actingAsApiUser($this->staff, ['events:read']);
        $this->getJson($this->api())->assertForbidden();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['programmes:read']);
        $this->getJson($this->api())->assertForbidden();
        $this->actingAsApiUser($this->staff, ['programmes:read']);
        $this->getJson($this->api())->assertOk()->assertJsonPath('data.ready', false);
    }

    private function api(): string
    {
        return '/api/staff/events/'.$this->event->uuid.'/programme/title-cards';
    }

    private function publish(?UploadedFile $file = null, array $override = [])
    {
        return $this->actingAs($this->staff)->postJson(route('admin.concert-title-cards.publish', $this->event), array_replace([
            'logo_source' => 'event', 'background_color' => '#000000', 'logo_band_color' => '#123456', 'text_color' => '#FFFFFF', 'separator_start_color' => '#000000', 'separator_end_color' => '#FFFFFF',
            'revision_uuid' => $this->revision->uuid, 'content_fingerprint' => app(ConcertTitlePackages::class)->fingerprint($this->event->fresh(), $this->revision), 'package' => $file ?? $this->archive(),
        ], $override));
    }

    private function archive(string $name = '01 Opening.jpg', ?string $bytes = null): UploadedFile
    {
        if ($bytes === null) {
            $image = imagecreatetruecolor(3840, 2160);
            ob_start();
            imagejpeg($image);
            $bytes = ob_get_clean();
            imagedestroy($image);
        }
        $file = UploadedFile::fake()->create('titles.zip');
        $zip = new ZipArchive;
        $zip->open($file->getRealPath(), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($name, $bytes);
        $zip->close();

        return UploadedFile::fake()->createWithContent('titles.zip', file_get_contents($file->getRealPath()));
    }
}
