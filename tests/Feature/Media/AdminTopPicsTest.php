<?php

namespace Tests\Feature\Media;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Crew\Models\CrewRole;
use App\Features\Media\Services\ConcertPhotoGallerySetup;
use App\Features\Programmes\Actions\TidyDemoTopPics;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTopPicsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $type = 'competition'): array
    {
        Storage::fake('local');
        $event = SchedulingEvent::create(['name' => $type.' Top Pics test', 'event_type' => $type, 'event_date' => '2026-09-22']);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $section = (string) Str::uuid();
        $item = (string) Str::uuid();
        $snapshot = ['event_type' => $type, 'nodes' => [
            ['uuid' => $section, 'kind' => 'section', 'number' => '01', 'title' => 'Junior Jazz'],
            ['uuid' => $item, 'kind' => 'item', 'number' => '02', 'title' => 'Opening routine', 'parent_uuid' => $section],
        ]];
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        $plan = ProgrammeUploadPlan::create(['programme_id' => $programme->id, 'programme_revision_id' => $revision->id, 'digest' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot]);
        $gallery = ProgrammePhotoGallery::create(['scheduling_event_id' => $event->id, 'programme_upload_plan_id' => $plan->id]);
        $role = CrewRole::where('code', $type.'-photographer-p1')->firstOrFail();
        $contribution = ProgrammePhotoContribution::create(['programme_upload_plan_id' => $plan->id, 'item_uuid' => $item, 'crew_role_id' => $role->id, 'assignment_uuids' => []]);
        $photo = $this->photo($gallery, $contribution, 'selected.jpg');

        return compact('event', 'gallery', 'plan', 'photo', 'contribution');
    }

    private function photo(ProgrammePhotoGallery $gallery, ProgrammePhotoContribution $contribution, string $name, array $extra = []): ProgrammePhotoFile
    {
        $image = imagecreatetruecolor(120, 80);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        unset($image);
        $key = 'top-pics-test/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($key, $bytes);

        return ProgrammePhotoFile::create(array_replace([
            'programme_photo_gallery_id' => $gallery->id, 'programme_photo_contribution_id' => $contribution->id,
            'client_photo_uuid' => (string) Str::uuid(), 'file_name' => $name, 'byte_count' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
            'is_top_pic' => true, 'status' => 'verified', 'verified_at' => now(), 'storage_disk' => 'local', 'storage_key' => $key,
        ], $extra));
    }

    public function test_only_admin_users_can_access_pages_and_originals(): void
    {
        $f = $this->fixture();
        $urls = [route('admin.publicity.tv-slideshows'), route('admin.top-pics.index'), route('admin.top-pics.show', $f['event']), route('admin.top-pics.download', ['schedulingEvent' => $f['event'], 'photo' => $f['photo']->uuid])];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect();
        }
        foreach (['customer', 'crew'] as $type) {
            $this->actingAs(User::factory()->create(['type' => $type, 'is_admin' => false]));
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_event_switch_and_albums_show_only_verified_top_pics(): void
    {
        $f = $this->fixture();
        $concert = $this->fixture('concert');
        $this->photo($f['gallery'], $f['contribution'], 'not-selected.jpg', ['is_top_pic' => false]);
        $this->photo($f['gallery'], $f['contribution'], 'not-verified.jpg', ['verified_at' => null]);
        $this->photo($f['gallery'], $f['contribution'], 'duplicate.jpg', ['duplicate_of_file_id' => $f['photo']->id]);
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.top-pics.index'))->assertOk()->assertSee($f['event']->name)->assertDontSee($concert['event']->name);
        $this->get(route('admin.top-pics.index', ['type' => 'concert', 'unlinked' => 1]))->assertOk()->assertSee($concert['event']->name)->assertDontSee($f['event']->name);
        $this->get(route('admin.top-pics.show', $f['event']))->assertOk()->assertSee('selected.jpg')->assertSee('Junior Jazz')->assertSee('Opening routine')
            ->assertSee('data-top-pic-preview', false)->assertSee('data-grant-media', false)
            ->assertDontSee('not-selected.jpg')->assertDontSee('not-verified.jpg')->assertDontSee('duplicate.jpg');
        $this->get(route('admin.top-pics.index', ['type' => 'invalid']))->assertSessionHasErrors('type');
    }

    public function test_concert_browsers_group_by_studio_and_share_selected_event_covers(): void
    {
        $studio = Studio::factory()->create(['name' => 'Album studio']);
        $first = $this->fixture('concert');
        $first['event']->update(['studio_id' => $studio->id, 'name' => 'Older concert', 'event_date' => '2026-09-01']);
        $second = $this->fixture('concert');
        $second['event']->update(['studio_id' => $studio->id, 'name' => 'Newer concert', 'event_date' => '2026-09-29']);
        $other = $this->fixture('concert');
        $other['event']->update(['studio_id' => Studio::factory()->create(['name' => 'Other studio'])->id, 'name' => 'Other event']);
        $chosen = $this->photo($first['gallery'], $first['contribution'], 'chosen-cover.jpg');
        $this->actingAs(User::factory()->staff()->create());
        $this->postJson(route('admin.top-pics.cover', $first['event']), ['photo_uuid' => $chosen->uuid])
            ->assertOk()->assertJsonPath('photo_uuid', $chosen->uuid);
        $this->assertSame($chosen->id, $first['gallery']->fresh()->top_pic_cover_photo_id);
        foreach (['admin.concert-photo-gallery.events' => [], 'admin.top-pics.index' => ['type' => 'concert']] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertOk()->assertSee('Album studio')->assertSee('Other studio')
                ->assertDontSee('Older concert')->assertDontSee('Newer concert');
            $this->get(route($route, $parameters + ['studio' => $studio->uuid]))->assertOk()
                ->assertSeeInOrder(['Newer concert', 'Older concert'])->assertDontSee('Other event')
                ->assertSee(route('admin.concert-photo-gallery.image', ['schedulingEvent' => $first['event'], 'photo' => $chosen->uuid, 'size' => 'cover']), false);
        }
        $this->postJson(route('admin.top-pics.cover', $first['event']), ['photo_uuid' => $other['photo']->uuid])->assertNotFound();
        $chosen->update(['is_top_pic' => false]);
        $this->postJson(route('admin.top-pics.cover', $first['event']), ['photo_uuid' => $chosen->uuid])->assertNotFound();
        $this->get(route('admin.top-pics.index', ['type' => 'concert', 'studio' => $studio->uuid]))->assertOk()
            ->assertDontSee($chosen->uuid)->assertSee($first['photo']->uuid);
        $this->postJson(route('admin.top-pics.cover', $first['event']), ['photo_uuid' => null])->assertOk();
        $this->assertNull($first['gallery']->fresh()->top_pic_cover_photo_id);
        $this->actingAs(User::factory()->customer()->create())->postJson(route('admin.top-pics.cover', $first['event']), ['photo_uuid' => $first['photo']->uuid])->assertForbidden();
    }

    public function test_download_uses_original_bytes_and_logs_admin_access_without_copying(): void
    {
        $f = $this->fixture();
        $this->actingAs($admin = User::factory()->staff()->create());
        $before = Storage::disk('local')->allFiles();
        $response = $this->get(route('admin.top-pics.download', ['schedulingEvent' => $f['event'], 'photo' => $f['photo']->uuid]))->assertOk()->assertDownload('selected.jpg');
        $this->assertSame(Storage::disk('local')->get($f['photo']->storage_key), $response->streamedContent());
        $this->assertSame($before, Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('programme_upload_audits', ['actor_user_id' => $admin->id, 'file_uuid' => $f['photo']->uuid, 'action' => 'admin_top_pic_download']);
        $unselected = $this->photo($f['gallery'], $f['contribution'], 'other.jpg', ['is_top_pic' => false]);
        $this->get(route('admin.top-pics.download', ['schedulingEvent' => $f['event'], 'photo' => $unselected->uuid]))->assertNotFound();
        $other = SchedulingEvent::create(['name' => 'Other event', 'event_type' => 'competition', 'event_date' => '2026-09-23']);
        $this->get(route('admin.top-pics.download', ['schedulingEvent' => $other, 'photo' => $f['photo']->uuid]))->assertNotFound();
    }

    public function test_old_plan_files_are_excluded_and_empty_albums_are_explained(): void
    {
        $f = $this->fixture();
        $replacement = $f['plan']->replicate();
        $replacement->uuid = (string) Str::uuid();
        $replacement->digest = hash('sha256', 'replacement');
        $replacement->save();
        $f['gallery']->update(['programme_upload_plan_id' => $replacement->id]);
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.top-pics.show', $f['event']))->assertOk()->assertSee('No Top Pics for this event yet')->assertDontSee('selected.jpg');
        $this->get(route('admin.top-pics.index'))->assertOk()->assertDontSee($f['event']->name);
    }

    public function test_concert_top_pics_work_before_sync_and_grants_use_synced_assets(): void
    {
        $f = $this->fixture('concert');
        $studio = Studio::factory()->create();
        $booking = ConcertBooking::create(['studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'test@example.test', 'contact_phone' => '0400000000', 'status' => 'pending']);
        $item = ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'item_type' => 'concert', 'title' => 'Showcase', 'scheduling_event_id' => $f['event']->id, 'event_date' => '2026-09-22', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'venue_name' => 'Theatre', 'venue_address' => 'Example']);
        $concert = Concert::factory()->create(['studio_id' => $studio->id, 'concert_booking_item_id' => $item->id]);
        $this->actingAs(User::factory()->staff()->create());
        $url = route('admin.top-pics.show', $f['event']);
        $this->get($url)->assertOk()->assertSee('For older uploads, use Gallery repair in Concert photos');
        app(ConcertPhotoGallerySetup::class)->sync($f['event']);
        $asset = $concert->mediaCollections()->first()->assets()->first();
        $this->get($url)->assertOk()->assertSee('data-source-uuid="'.$asset->uuid.'"', false)->assertDontSee('For older uploads, use Gallery repair in Concert photos');
        $this->get(route('admin.concert-photo-gallery.image', ['schedulingEvent' => $f['event'], 'photo' => $f['photo']->uuid]))->assertOk();
        $this->get(route('admin.concert-photo-gallery.show', $f['event']))->assertOk()->assertSee($url, false);
    }

    public function test_demo_cleanup_preserves_records_and_only_clears_repeated_top_pic_flags(): void
    {
        $f = $this->fixture();
        $f['photo']->update(['storage_key' => 'local-development/realistic-media/PDC826/item/photo.jpg']);
        $duplicate = $f['photo']->replicate();
        $duplicate->uuid = (string) Str::uuid();
        $duplicate->client_photo_uuid = (string) Str::uuid();
        $duplicate->save();
        $otherItem = $f['contribution']->replicate();
        $otherItem->uuid = (string) Str::uuid();
        $otherItem->item_uuid = (string) Str::uuid();
        $otherItem->save();
        $other = $duplicate->replicate();
        $other->uuid = (string) Str::uuid();
        $other->client_photo_uuid = (string) Str::uuid();
        $other->programme_photo_contribution_id = $otherItem->id;
        $other->save();
        $this->app->instance('env', 'local');
        $tidy = app(TidyDemoTopPics::class);
        $this->assertSame(1, $tidy->execute());
        $this->assertTrue($f['photo']->fresh()->is_top_pic);
        $this->assertFalse($duplicate->fresh()->is_top_pic);
        $this->assertTrue($other->fresh()->is_top_pic);
        $this->assertNull($duplicate->fresh()->duplicate_of_file_id);
        $this->assertSame($f['photo']->storage_key, $duplicate->fresh()->storage_key);
        $this->assertSame(3, ProgrammePhotoFile::count());
        $this->assertSame(0, $tidy->execute());
    }

    public function test_demo_cleanup_refuses_non_local_environments(): void
    {
        $this->expectException(\RuntimeException::class);
        app(TidyDemoTopPics::class)->execute();
    }

    public function test_publicity_has_separate_top_pics_tv_and_sharing_pages(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.top-pics.index', ['type' => 'concert']))->assertOk()
            ->assertSee('Publicity')->assertSee('TV Slideshows')
            ->assertSee(route('admin.publicity.tv-slideshows'), false)
            ->assertSee(route('admin.publicity.sharing.index'), false);
        $this->get(route('admin.publicity.tv-slideshows', ['type' => 'concert']))->assertOk()
            ->assertSee('Open screen player')->assertSee('Standard advertising')
            ->assertSee('Choose a competition')
            ->assertSee(route('admin.top-pics.index', ['type' => 'competition']), false)
            ->assertSee(route('admin.publicity.sharing.index'), false);
    }
}
