<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Programmes\Models\ConcertTitleCardPackage;
use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeSource;
use App\Features\Programmes\Services\ConcertTitlePackages;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConcertEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigns_editor_and_completion_preserves_other_fields(): void
    {
        [$staff, $event, $second] = $this->fixture();
        $editor = $this->editor();
        $url = route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']);
        $bar = route('admin.concert-tracker.source-bar.update', [$event, 'concert_edited']);
        $this->actingAs($staff)->get($url)->assertOk()->assertSeeInOrder(['Tracker task controls', 'Concert editing', 'Mark editing complete'])->assertDontSee('Email templates');
        $this->patchJson($bar, ['panel' => 'assignment', 'assignee_user_id' => $editor->id])->assertOk()->assertJsonPath('data.redirect', $url);
        $this->patchJson($bar, ['panel' => 'due', 'due_date_override' => '2026-10-30'])->assertOk();
        $this->patchJson($bar, ['panel' => 'follow_up', 'follow_up_status' => 'open', 'follow_up_date' => '2026-10-28', 'note' => 'Check the opening title.'])->assertOk();
        $this->patch($bar, ['panel' => 'status', 'status_override' => 'complete'])->assertSessionHasNoErrors();
        $detail = app(ConcertTracker::class)->detail($event->fresh(), 'concert_edited');
        $this->assertSame('complete', $detail['status']['tone']);
        $task = $detail['task'];
        $this->assertSame($editor->id, $task->assignee_user_id);
        $this->assertSame('2026-10-30', $task->due_date_override->toDateString());
        $this->assertSame('open', $task->follow_up_status);
        $this->assertSame($staff->id, $task->completed_by_user_id);
        $this->get($url)->assertOk()->assertSee('Editing complete')->assertSee('Morgan Vale')->assertSee('Reopen editing')->assertDontSee('value="complete">Mark editing complete</button>', false);
        $completedAt = $task->completed_at->toDateTimeString();
        $this->travel(1)->hours();
        $this->patch($bar, ['panel' => 'status', 'status_override' => 'complete'])->assertSessionHasNoErrors();
        $this->assertSame($completedAt, $task->fresh()->completed_at->toDateTimeString());
        $this->patch($bar, ['panel' => 'status', 'status_override' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->completed_at);
        $this->assertNull($task->fresh()->completed_by_user_id);
        $this->assertSame('not_started', app(ConcertTracker::class)->detail($second, 'concert_edited')['status']['key']);
        $this->assertSame('not_started', app(ConcertTracker::class)->detail($event->fresh(), 'quality_check')['status']['key']);
        $this->assertDatabaseHas('concert_tracker_task_changes', ['concert_tracker_task_id' => $task->id, 'user_id' => $staff->id]);
    }

    public function test_assigned_editor_can_complete_task_but_cannot_reassign_or_change_deadlines(): void
    {
        [$staff, $event, $second] = $this->fixture();
        $editor = $this->editor();
        $task = app(ConcertTracker::class)->detail($event, 'concert_edited')['task'];
        $task->update(['assignee_user_id' => $editor->id, 'due_date_override' => '2026-10-30']);
        $url = route('crew.editing.show', $event);
        $update = route('crew.editing.update', $event);
        $this->actingAs($editor)->get(route('crew.editing.index'))->assertOk()->assertSee('Junior Concert')->assertDontSee('Senior Concert');
        $this->get($url)->assertOk()->assertSee('Tracker task controls')->assertSee('Alex Editor')->assertSee('Mark editing complete')->assertDontSee('id="tracker-assignment"', false)->assertDontSee('id="tracker-due"', false);
        $this->patchJson($update, ['panel' => 'assignment', 'assignee_user_id' => $staff->id])->assertUnprocessable();
        $this->patchJson($update, ['panel' => 'due', 'due_date_override' => '2026-11-01'])->assertUnprocessable();
        $this->patchJson($update, ['panel' => 'follow_up', 'follow_up_status' => 'open', 'follow_up_date' => null, 'note' => 'Opening title checked.'])->assertOk();
        $this->patch($update, ['panel' => 'status', 'status_override' => 'complete', 'assignee_user_id' => $staff->id, 'due_date_override' => null])->assertRedirect($url);
        $this->assertSame($editor->id, $task->fresh()->completed_by_user_id);
        $this->assertSame($editor->id, $task->fresh()->assignee_user_id);
        $this->assertSame('2026-10-30', $task->fresh()->due_date_override->toDateString());
        $this->get($url)->assertOk()->assertSee('Editing complete')->assertSee('Alex Editor');
        $this->get(route('crew.editing.show', $second))->assertForbidden();
        $this->patch(route('crew.editing.update', $second), ['panel' => 'status', 'status_override' => 'complete'])->assertForbidden();
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']))->assertForbidden();
        $task->update(['assignee_user_id' => $staff->id]);
        $this->get($url)->assertForbidden();
        $this->patch($update, ['panel' => 'status', 'status_override' => 'in_progress'])->assertForbidden();
        $this->actingAs(User::factory()->customer()->create())->get($url)->assertForbidden();
    }

    public function test_photo_only_editing_is_not_required_and_cannot_be_completed(): void
    {
        [$staff, $event, $second, $booking] = $this->fixture();
        $booking->update(['wants_concert_videography' => false, 'wants_concert_photography' => true]);
        $url = route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']);
        $this->actingAs($staff)->get($url)->assertOk()->assertSee('Not required')->assertDontSee('Mark editing complete');
        $this->patchJson(route('admin.concert-tracker.source-bar.update', [$event, 'concert_edited']), ['panel' => 'status', 'status_override' => 'complete'])->assertUnprocessable();
        $editor = $this->editor();
        app(ConcertTracker::class)->detail($event, 'concert_edited')['task']->update(['assignee_user_id' => $editor->id]);
        $this->actingAs($editor)->get(route('crew.editing.show', $event))->assertOk()->assertSee('Not required')->assertDontSee('Mark editing complete');
        $this->patchJson(route('crew.editing.update', $event), ['panel' => 'status', 'status_override' => 'complete'])->assertUnprocessable();
    }

    public function test_editing_downloads_use_event_files_and_enforce_editor_access(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        [$staff, $event, $other] = $this->fixture();
        $editor = $this->editor();
        $task = app(ConcertTracker::class)->detail($event, 'concert_edited')['task'];
        $task->update(['assignee_user_id' => $editor->id]);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $source = ProgrammeSource::create(['programme_id' => $programme->id, 'disk' => 'local', 'path' => 'editing/programme.pdf', 'name' => 'Programme.pdf', 'created_by_user_id' => $staff->id]);
        $programme->update(['programme_source_id' => $source->id]);
        Storage::disk('local')->put($source->path, '%PDF-source');
        $video = StudioEventFile::create([
            'scheduling_event_id' => $event->id, 'kind' => 'studio_video', 'status' => 'approved', 'is_current' => true,
            'disk' => 'local', 'storage_key' => 'editing/opening.mp4', 'original_name' => 'Opening.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 13, 'uploaded_by_user_id' => $staff->id, 'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($video->storage_key, 'video-content');
        $programmeUrl = route('concert-editing.download', [$event, 'kind' => 'programme']);
        $videoUrl = route('concert-editing.download', [$event, 'kind' => 'video', 'file' => $video->uuid]);
        $this->actingAs($staff)->get(route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']))->assertOk()->assertSee('Download programme')->assertSee('Opening.mp4')->assertSee('Download video');
        $this->get($programmeUrl)->assertOk()->assertDownload('Programme.pdf');
        $this->assertSame('video-content', $this->get($videoUrl)->assertOk()->assertDownload('Opening.mp4')->streamedContent());
        $this->actingAs($editor)->get(route('crew.editing.show', $event))->assertOk()->assertSee('Download programme')->assertSee('Opening.mp4');
        $this->assertSame('%PDF-source', $this->get($programmeUrl)->assertOk()->streamedContent());
        $this->get($videoUrl)->assertOk()->assertDownload('Opening.mp4');
        $this->get(route('concert-editing.download', [$other, 'kind' => 'programme']))->assertForbidden();
        $this->actingAs($staff)->get(route('concert-editing.download', [$other, 'kind' => 'video', 'file' => $video->uuid]))->assertNotFound();
        $video->update(['status' => 'received']);
        $this->actingAs($editor)->get($videoUrl)->assertNotFound();
        $this->get(route('crew.editing.show', $event))->assertOk()->assertDontSee('Opening.mp4');
        $this->actingAs($staff)->get($videoUrl)->assertOk();
        $video->update(['is_current' => false, 'status' => 'superseded']);
        $this->get($videoUrl)->assertNotFound();
        $task->update(['assignee_user_id' => $staff->id]);
        $this->actingAs($editor)->get($programmeUrl)->assertForbidden();
        $customer = User::factory()->customer()->create();
        CrewProfile::factory()->create(['user_id' => $customer->id]);
        $task->update(['assignee_user_id' => $customer->id]);
        $this->actingAs($customer)->get($programmeUrl)->assertForbidden();
    }

    public function test_titles_download_uses_only_the_current_published_package(): void
    {
        Storage::fake('local');
        [$staff, $event] = $this->fixture();
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => ['nodes' => []]]);
        $template = ConcertTitleCardTemplate::create(['scheduling_event_id' => $event->id]);
        $settings = $template->fresh()->only(ConcertTitlePackages::SETTINGS);
        $package = ConcertTitleCardPackage::create([
            'scheduling_event_id' => $event->id, 'programme_revision_id' => $revision->id, 'published_by_user_id' => $staff->id,
            'disk' => 'local', 'path' => 'editing/titles.zip', 'sha256' => hash('sha256', 'title-archive'), 'size_bytes' => 13,
            'item_count' => 1, 'settings' => $settings, 'content_fingerprint' => app(ConcertTitlePackages::class)->fingerprint($event, $revision),
        ]);
        Storage::disk('local')->put($package->path, 'title-archive');
        $task = ConcertTrackerTask::create(['scope_type' => 'event', 'scope_id' => $event->id, 'task_key' => 'video_titles', 'status' => 'complete']);
        $url = route('concert-editing.download', [$event, 'kind' => 'titles']);
        $page = route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']);
        $this->actingAs($staff)->get($page)->assertOk()->assertSee('Download video titles (ZIP)');
        $this->assertSame('title-archive', $this->get($url)->assertOk()->assertDownload('Video titles '.$package->uuid.'.zip')->streamedContent());
        $task->update(['status' => 'in_progress']);
        $this->get($url)->assertNotFound();
        $task->update(['status' => 'complete']);
        $template->update(['background_color' => '#112233']);
        $this->get($url)->assertNotFound();
        $this->get($page)->assertOk()->assertDontSee('Download video titles (ZIP)');
    }

    private function editor(): User
    {
        $editor = User::factory()->crew()->create(['name' => 'Alex Editor']);
        CrewProfile::factory()->create(['user_id' => $editor->id]);

        return $editor;
    }

    private function fixture(): array
    {
        $staff = User::factory()->staff()->create(['name' => 'Morgan Vale']);
        $studio = Studio::factory()->create(['name' => 'Aurora Dance']);
        $booking = ConcertBooking::create([
            'status' => 'approved', 'studio_name' => $studio->name, 'contact_name' => 'Alex Director',
            'contact_email' => 'alex@example.test', 'contact_phone' => '0400000000',
            'wants_concert_videography' => true, 'reviewed_by_user_id' => $staff->id, 'reviewed_at' => now(),
        ]);
        $events = [];
        foreach (['Junior Concert', 'Senior Concert'] as $title) {
            $event = SchedulingEvent::create(['studio_id' => $studio->id, 'name' => $title, 'event_type' => 'concert', 'event_date' => '2026-10-20']);
            ConcertBookingItem::create([
                'concert_booking_id' => $booking->id, 'item_type' => 'concert', 'title' => $title,
                'venue_name' => 'Theatre', 'venue_address' => 'Example street', 'starts_at' => '18:00',
                'finishes_at' => '20:00', 'event_date' => $event->event_date, 'scheduling_event_id' => $event->id,
            ]);
            $events[] = $event;
        }

        return [$staff, ...$events, $booking];
    }
}
