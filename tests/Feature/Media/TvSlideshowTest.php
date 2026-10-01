<?php

namespace Tests\Feature\Media;

use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerAccountActivity;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Media\Services\MediaWatermarkRenderer;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Publicity\Models\PublicitySlide;
use App\Features\Publicity\Models\TvScreen;
use App\Features\Publicity\Services\PublicityEventAccess;
use App\Features\Publicity\Services\SlideshowManagement;
use App\Features\Publicity\Services\SlideshowPlaylist;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class TvSlideshowTest extends TestCase
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

    public function test_admin_manages_settings_and_private_slides_but_customer_cannot(): void
    {
        $f = $this->fixture();
        $url = route('admin.publicity.update', $f['event']);
        $this->actingAs(User::factory()->create(['type' => 'customer', 'is_admin' => false]));
        $this->get(route('admin.publicity.show', $f['event']))->assertForbidden();
        $this->put($url, ['enabled' => 1, 'photo_seconds' => 8, 'promotion_every' => 5])->assertForbidden();
        $admin = User::factory()->staff()->create();
        $this->actingAs($admin);
        $this->get(route('admin.publicity.show', $f['event']))->assertOk()->assertSee('Preview slideshow')->assertSee('Pair a screen');
        $this->put($url, ['enabled' => 1, 'photo_seconds' => 1, 'promotion_every' => 0])->assertSessionHasErrors(['photo_seconds', 'promotion_every']);
        $this->put($url, ['enabled' => 1, 'photo_seconds' => 9, 'promotion_every' => 2])->assertRedirect();
        $this->assertDatabaseHas('tv_slideshows', ['scheduling_event_id' => $f['event']->id, 'enabled' => true, 'photo_seconds' => 9]);
        $this->post(route('admin.publicity.event-slides.store', $f['event']), ['title' => 'Collect your media', 'body' => 'Visit DancePro', 'enabled' => 1, 'duration_seconds' => 12, 'sort_order' => 0, 'image' => UploadedFile::fake()->image('advert.jpg', 120, 80)])->assertRedirect();
        $slide = PublicitySlide::firstOrFail();
        Storage::disk('local')->assertExists($slide->image_key);
        $this->assertSame($f['event']->id, $slide->scheduling_event_id);
        $this->get(route('admin.publicity.image', ['schedulingEvent' => $f['event'], 'key' => 'slide-'.$slide->uuid]))->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->get(route('admin.publicity.preview', $f['event']))->assertOk()->assertSee('Admin preview');
    }

    public function test_manifest_reads_live_flags_and_excludes_other_events_duplicates_and_old_plans(): void
    {
        $f = $this->fixture();
        $other = $this->fixture('concert');
        $this->photo($f['gallery'], $f['contribution'], 'not-starred.jpg', ['is_top_pic' => false]);
        $this->photo($f['gallery'], $f['contribution'], 'duplicate.jpg', ['duplicate_of_file_id' => $f['photo']->id]);
        $this->photo($f['gallery'], $f['contribution'], 'unverified.jpg', ['verified_at' => null]);
        $playlist = app(SlideshowPlaylist::class);
        $before = $playlist->manifest($f['event'], true);
        $this->assertCount(2, $before['slides']);
        $this->assertSame('photo-'.$f['photo']->uuid, $before['slides'][1]['id']);
        $this->assertSame($before['revision'], $playlist->manifest($f['event'], true)['revision']);
        $f['photo']->update(['is_top_pic' => false]);
        $after = $playlist->manifest($f['event'], true);
        $this->assertCount(1, $after['slides']);
        $this->assertStringStartsWith('auto-', $after['slides'][0]['id']);
        $this->assertNotSame($before['revision'], $after['revision']);
        $f['photo']->update(['is_top_pic' => true]);
        $replacement = $f['plan']->replicate();
        $replacement->uuid = (string) Str::uuid();
        $replacement->digest = hash('sha256', 'new-plan');
        $replacement->save();
        $f['gallery']->update(['programme_upload_plan_id' => $replacement->id]);
        $this->assertCount(1, $playlist->manifest($f['event'], true)['slides']);
    }

    public function test_advertising_is_interleaved_without_omitting_information_slides(): void
    {
        $f = $this->fixture();
        $other = $this->fixture('concert');
        $admin = User::factory()->staff()->create();
        $management = app(SlideshowManagement::class);
        foreach (['Global one', 'Global two', 'Global three'] as $title) {
            $management->saveSlide(null, ['title' => $title, 'enabled' => true, 'duration_seconds' => 12, 'sort_order' => 0], $admin);
        }
        $management->saveSlide($f['event'], ['title' => 'Event information', 'enabled' => true, 'duration_seconds' => 10, 'sort_order' => 1], $admin);
        $management->saveSlide($other['event'], ['title' => 'Other event', 'enabled' => true, 'duration_seconds' => 10, 'sort_order' => 1], $admin);
        $management->saveSlide(null, ['title' => 'Hidden', 'enabled' => false, 'duration_seconds' => 10, 'sort_order' => 1], $admin);
        $manifest = app(SlideshowPlaylist::class)->manifest($f['event'], true);
        $this->assertCount(6, $manifest['slides']);
        $this->assertSame('Global one', $manifest['slides'][0]['title']);
        $this->assertSame('photo-'.$f['photo']->uuid, $manifest['slides'][1]['id']);
        $this->assertSame('Event information', $manifest['slides'][4]['title']);
        $this->assertSame(1, ProgrammePhotoFile::where('programme_photo_gallery_id', $f['gallery']->id)->count());
        $this->assertSame([], app(SlideshowPlaylist::class)->manifest($f['event'])['slides']);
    }

    public function test_pairing_is_one_time_scoped_and_revoke_stops_manifest_and_images(): void
    {
        $other = $this->fixture('concert');
        $f = $this->fixture();
        $pair = $this->postJson('/api/publicity/screens/pair')->assertOk()->json('data');
        $headers = ['Authorization' => 'Bearer '.$pair['token']];
        $this->getJson('/api/publicity/screens/manifest', $headers)->assertOk()->assertJsonPath('data.paired', false);
        $this->getJson('/api/publicity/screens/images/photo-'.$f['photo']->uuid, $headers)->assertForbidden();
        $admin = User::factory()->staff()->create();
        $this->actingAs($admin);
        $this->post(route('admin.publicity.pair', $f['event']), ['code' => $pair['code'], 'name' => 'Foyer'])->assertRedirect();
        $this->post(route('admin.publicity.pair', $other['event']), ['code' => $pair['code'], 'name' => 'Other'])->assertSessionHasErrors('code');
        $this->getJson('/api/publicity/screens/manifest', $headers)->assertOk()->assertJsonPath('data.enabled', false)->assertJsonCount(0, 'data.slides');
        app(SlideshowManagement::class)->saveSettings($f['event'], ['enabled' => true, 'photo_seconds' => 8, 'promotion_every' => 5]);
        $manifest = $this->getJson('/api/publicity/screens/manifest', $headers)->assertOk()->assertJsonPath('data.event.uuid', $f['event']->uuid)->assertJsonCount(2, 'data.slides')->json('data');
        $this->getJson('/api/publicity/screens/images/photo-'.$f['photo']->uuid, $headers)->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->getJson('/api/publicity/screens/images/photo-'.$other['photo']->uuid, $headers)->assertNotFound();
        $this->getJson('/api/publicity/screens/manifest?cached_count=1&cached_revision='.$manifest['revision'], $headers)->assertOk();
        $screen = TvScreen::firstOrFail();
        $this->assertSame(1, $screen->fresh()->cached_count);
        $this->post(route('admin.publicity.screens.revoke', $screen))->assertRedirect();
        $this->getJson('/api/publicity/screens/manifest', $headers)->assertUnauthorized();
        $this->getJson('/api/publicity/screens/images/photo-'.$f['photo']->uuid, $headers)->assertUnauthorized();
        $this->getJson('/api/publicity/screens/manifest', ['Authorization' => 'Bearer '.str_repeat('x', 64)])->assertUnauthorized();
    }

    public function test_expired_pairing_cannot_be_used_and_private_info_is_not_in_public_shell(): void
    {
        $f = $this->fixture();
        $pair = app(SlideshowManagement::class)->beginPairing();
        $this->travel(11)->minutes();
        $this->actingAs(User::factory()->staff()->create());
        $this->post(route('admin.publicity.pair', $f['event']), ['name' => 'Late screen', 'code' => $pair['code']])->assertSessionHasErrors('code');
        $this->getJson('/api/publicity/screens/manifest', ['Authorization' => 'Bearer '.$pair['token']])->assertUnauthorized();
        $this->get('/tv')->assertOk()->assertDontSee($f['event']->name)->assertDontSee($pair['token']);
    }

    public function test_paired_screen_selects_only_enabled_events_newest_first_including_events_without_photos(): void
    {
        $f = $this->fixture();
        $empty = SchedulingEvent::create(['name' => 'Upcoming competition without photos', 'event_type' => 'competition', 'event_date' => '2026-09-26']);
        $disabled = SchedulingEvent::create(['name' => 'Disabled concert', 'event_type' => 'concert', 'event_date' => '2026-09-27']);
        $management = app(SlideshowManagement::class);
        foreach ([$empty, $f['event'], $disabled] as $event) {
            $management->saveSettings($event, ['enabled' => true, 'photo_seconds' => 8, 'promotion_every' => 5]);
        }
        $this->assertFalse(app(SlideshowPlaylist::class)->manifest($disabled)['enabled']);
        $pair = $this->postJson('/api/publicity/screens/pair')->json('data');
        $headers = ['Authorization' => 'Bearer '.$pair['token']];
        $this->getJson('/api/publicity/screens/events', $headers)->assertForbidden();
        $this->postJson('/api/publicity/screens/event', ['event_uuid' => $empty->uuid], $headers)->assertForbidden();
        $admin = User::factory()->staff()->create();
        $management->pair($f['event'], ['code' => $pair['code'], 'name' => 'Crew TV'], $admin);
        $this->getJson('/api/publicity/screens/events', $headers)->assertOk()->assertJsonCount(2, 'data.events')
            ->assertJsonPath('data.events.0.uuid', $empty->uuid)->assertJsonPath('data.events.1.uuid', $f['event']->uuid);
        $this->postJson('/api/publicity/screens/event', ['event_uuid' => $disabled->uuid], $headers)->assertNotFound();
        $this->postJson('/api/publicity/screens/event', ['event_uuid' => 'invalid'], $headers)->assertUnprocessable();
        $management->saveSlide(null, ['title' => 'General advert', 'enabled' => true, 'duration_seconds' => 8, 'sort_order' => 0], $admin);
        $management->saveSlide($empty, ['title' => 'Concert advert', 'enabled' => true, 'duration_seconds' => 8, 'sort_order' => 1], $admin);
        $this->postJson('/api/publicity/screens/event', ['event_uuid' => $empty->uuid], $headers)->assertOk();
        $this->getJson('/api/publicity/screens/manifest', $headers)->assertOk()->assertJsonPath('data.event.uuid', $empty->uuid)
            ->assertJsonCount(3, 'data.slides')->assertJsonPath('data.slides.0.title', 'General advert')->assertJsonPath('data.slides.1.title', 'Concert advert');
        $this->getJson('/api/publicity/screens/images/photo-'.$f['photo']->uuid, $headers)->assertNotFound();
        $this->actingAs($admin)->get(route('admin.publicity.tv-slideshows', ['type' => 'concert']))->assertOk()->assertSee($empty->name)->assertDontSee($disabled->name);
        $management->revoke(TvScreen::firstOrFail());
        $this->getJson('/api/publicity/screens/events', $headers)->assertUnauthorized();
        $this->postJson('/api/publicity/screens/event', ['event_uuid' => $f['event']->uuid], $headers)->assertUnauthorized();
    }

    public function test_tv_derivatives_do_not_apply_customer_watermarks_or_modify_originals(): void
    {
        $f = $this->fixture();
        $source = Storage::disk('local')->get($f['photo']->storage_key);
        $this->mock(MediaWatermarkRenderer::class)->shouldNotReceive('apply');
        $playlist = app(SlideshowPlaylist::class);
        $key = $playlist->image($f['event'], 'photo-'.$f['photo']->uuid);
        $this->assertStringStartsWith('publicity/photos/', $key);
        $image = imagecreatefromstring(Storage::disk('local')->get($key));
        $this->assertSame(120, imagesx($image));
        $this->assertSame(80, imagesy($image));
        $this->assertSame(0, imagecolorat($image, 60, 40));
        $this->assertSame($source, Storage::disk('local')->get($f['photo']->storage_key));
        $this->assertSame('unwatermarked-v1', $playlist->manifest($f['event'], true)['image_variant']);
    }

    public function test_defaults_generated_advert_and_offline_branding(): void
    {
        $f = $this->fixture();
        $management = app(SlideshowManagement::class);
        $this->assertSame(2, $management->settings($f['event'])->photo_seconds);
        $admin = User::factory()->staff()->create();
        $management->saveSlide(null, ['title' => 'Default general advert', 'enabled' => true, 'sort_order' => 0], $admin);
        $management->saveSlide($f['event'], ['title' => 'Default event advert', 'enabled' => true, 'sort_order' => 1], $admin);
        $this->assertSame([5, 8], PublicitySlide::orderBy('id')->pluck('duration_seconds')->all());
        $playlist = app(SlideshowPlaylist::class);
        $manifest = $playlist->manifest($f['event'], true);
        $this->assertStringStartsWith('data:image/png;base64,', $manifest['overlays']['dancepro']);
        $auto = collect($manifest['slides'])->firstWhere('id', 'auto-'.$f['event']->uuid);
        $this->assertSame(8, $auto['seconds']);
        $key = $playlist->image($f['event'], $auto['id']);
        $this->assertSame([1920, 1080], array_slice(getimagesizefromstring(Storage::disk('local')->get($key)), 0, 2));
        $this->assertSame($key, $playlist->image($f['event'], $auto['id']));
        $f['event']->update(['name' => 'Changed event title']);
        $this->assertNotSame($auto['version'], collect($playlist->manifest($f['event'], true)['slides'])->firstWhere('id', $auto['id'])['version']);
        $f['photo']->update(['is_top_pic' => false]);
        $this->assertCount(3, $playlist->manifest($f['event'], true)['slides']);
    }

    public function test_standard_advertising_is_managed_and_served_separately_by_audience(): void
    {
        $f = $this->fixture();
        $admin = User::factory()->staff()->create();
        $this->actingAs($admin);
        $create = route('admin.publicity.slides.store');
        $fields = ['title' => 'Competition standard advert', 'enabled' => 1, 'sort_order' => 0];
        $this->post($create, $fields)->assertSessionHasErrors('audience');
        $this->post($create, $fields + ['audience' => 'invalid'])->assertSessionHasErrors('audience');
        $this->post($create, $fields + ['audience' => 'competition'])->assertRedirect();
        $this->post($create, ['title' => 'Concert standard advert', 'enabled' => 1, 'sort_order' => 0,
            'audience' => 'concert', 'image' => UploadedFile::fake()->image('concert-ad.jpg', 120, 80)])->assertRedirect();
        $concertAd = PublicitySlide::where('audience', 'concert')->sole();
        $this->assertSame(5, $concertAd->duration_seconds);
        $competitionPage = route('admin.publicity.tv-slideshows', ['advertising_type' => 'competition']);
        $concertPage = route('admin.publicity.tv-slideshows', ['advertising_type' => 'concert']);
        $this->get($competitionPage)->assertOk()->assertSee('Competition standard advert')->assertDontSee('Concert standard advert');
        $this->get($concertPage)->assertOk()->assertSee('Concert standard advert')->assertDontSee('Competition standard advert')
            ->assertSee('name="audience" value="concert"', false)->assertSee('Choose a competition');
        $this->from($concertPage)->put(route('admin.publicity.slides.update', $concertAd), [
            'title' => 'Updated concert advert', 'enabled' => 1, 'sort_order' => 2])->assertRedirect($concertPage);
        $this->assertSame('concert', $concertAd->fresh()->audience);
        $titles = collect(app(SlideshowPlaylist::class)->manifest($f['event'], true)['slides'])->pluck('title');
        $this->assertContains('Competition standard advert', $titles);
        $this->assertNotContains('Updated concert advert', $titles);
        $this->get(route('admin.publicity.image', ['schedulingEvent' => $f['event'], 'key' => 'slide-'.$concertAd->uuid]))->assertNotFound();
        $concert = SchedulingEvent::create(['name' => 'Concert scope', 'event_type' => 'concert', 'event_date' => now()]);
        $this->assertSame([$concertAd->id], PublicitySlide::forEvent($concert)->pluck('id')->all());
        $this->assertFalse(app(SlideshowPlaylist::class)->manifest($concert)['enabled']);
    }

    public function test_competition_templates_render_only_the_qr_box_and_refresh_with_access_changes(): void
    {
        $f = $this->fixture();
        $f['photo']->update(['is_top_pic' => false]);
        $settings = CompetitionGallerySetting::create(['scheduling_event_id' => $f['event']->id, 'login_code_hash' => bcrypt('EXAMPLE')]);
        $playlist = app(SlideshowPlaylist::class);
        $manifest = $playlist->manifest($f['event'], true);
        $this->assertCount(6, $manifest['slides']);
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.publicity.show', $f['event']))->assertOk()->assertSee('Competition advert 6');
        foreach ($manifest['slides'] as $i => $slide) {
            $number = $i + 1;
            $this->assertSame(8, $slide['seconds']);
            $this->assertSame('', $slide['title']);
            $this->assertNull($slide['body']);
            $key = $playlist->image($f['event'], $slide['id']);
            $bytes = Storage::disk('local')->get($key);
            $this->assertSame([1920, 1080], array_slice(getimagesizefromstring($bytes), 0, 2));
            $this->assertSame($key, $playlist->image($f['event'], $slide['id']));
            $this->get(route('admin.publicity.image', ['schedulingEvent' => $f['event'], 'key' => $slide['id']]))->assertOk()->assertHeader('content-type', 'image/png');
            $original = imagecreatefrompng(resource_path('images/publicity/competition-templates/qr-'.$number.'.png'));
            $rendered = imagecreatefromstring($bytes);
            [$x, $y, $size] = $number === 1 ? [124, 104, 663] : ($number === 2 ? [1142, 121, 663] : [1141, 120, 664]);
            // Restore the QR region and compare every other pixel: no extra logos, text or artwork changes.
            imagecopy($rendered, $original, $x, $y, $x, $y, $size, $size);
            ob_start();
            imagepng($rendered);
            $restored = ob_get_clean();
            ob_start();
            imagepng($original);
            $expected = ob_get_clean();
            $this->assertSame(hash('sha256', $expected), hash('sha256', $restored));
        }
        $other = SchedulingEvent::create(['name' => 'Other competition', 'event_type' => 'competition', 'event_date' => now()]);
        CompetitionGallerySetting::create(['scheduling_event_id' => $other->id, 'login_code_hash' => bcrypt('OTHER')]);
        $this->get(route('admin.publicity.image', ['schedulingEvent' => $other, 'key' => $manifest['slides'][0]['id']]))->assertNotFound();
        $settings->update(['login_code_hash' => bcrypt('CHANGED')]);
        $updated = $playlist->manifest($f['event']->fresh(), true);
        foreach ($updated['slides'] as $i => $slide) {
            $this->assertNotSame($manifest['slides'][$i]['version'], $slide['version']);
        }
        $settings->update(['login_code_hash' => null]);
        $this->get(route('admin.publicity.image', ['schedulingEvent' => $f['event'], 'key' => $manifest['slides'][0]['id']]))->assertNotFound();
        $fallback = $playlist->manifest($f['event']->fresh(), true);
        $this->assertSame('auto-'.$f['event']->uuid, $fallback['slides'][0]['id']);
        $this->assertCount(1, $fallback['slides']);
        $this->actingAs(User::factory()->create(['type' => 'customer', 'is_admin' => false]));
        $this->get(route('admin.publicity.image', ['schedulingEvent' => $other, 'key' => 'template-'.$other->uuid.'-1']))->assertForbidden();
    }

    public function test_qr_adds_competition_after_login_idempotently_and_rejects_changed_codes(): void
    {
        $f = $this->fixture();
        $management = app(SlideshowManagement::class);
        $management->saveSettings($f['event'], ['enabled' => true, 'photo_seconds' => 2, 'promotion_every' => 5]);
        $settings = CompetitionGallerySetting::create(['scheduling_event_id' => $f['event']->id, 'login_code_hash' => bcrypt('EXAMPLE')]);
        $access = app(PublicityEventAccess::class);
        $link = $access->link($f['event']);
        $this->get($link)->assertRedirect(route('login'))->assertSessionHas('publicity_join');
        $customer = User::factory()->create(['type' => 'customer', 'is_admin' => false, 'email_verified_at' => now()]);
        $this->actingAs($customer)->get(route('publicity.join.complete'))->assertRedirect(route('customer.media.index'));
        $this->assertDatabaseHas('customer_competition_registrations', ['user_id' => $customer->id, 'scheduling_event_id' => $f['event']->id]);
        $this->get($link)->assertRedirect(route('publicity.join.complete'));
        $this->get(route('publicity.join.complete'))->assertRedirect();
        $this->assertSame(1, CustomerCompetitionRegistration::count());
        $this->assertSame(1, CustomerAccountActivity::where('type', 'publicity_qr_access_added')->count());
        $this->get($link.'tampered')->assertForbidden();
        $settings->update(['login_code_hash' => bcrypt('CHANGED')]);
        $this->get($link)->assertForbidden();
        $fresh = $access->link($f['event']);
        $management->saveSettings($f['event'], ['enabled' => false, 'photo_seconds' => 2, 'promotion_every' => 5]);
        $this->get($fresh)->assertRedirect(route('publicity.join.complete'));
    }

    public function test_concert_qr_adds_photo_studio_but_no_paid_video_or_media(): void
    {
        $studio = Studio::factory()->create(['status' => 'active']);
        $studio->forceFill(['gallery_code_hash' => bcrypt('EXAMPLE')])->save();
        $event = SchedulingEvent::create(['name' => 'Concert QR', 'event_type' => 'concert', 'event_date' => now(), 'studio_id' => $studio->id]);
        app(SlideshowManagement::class)->saveSettings($event, ['enabled' => true, 'photo_seconds' => 2, 'promotion_every' => 5]);
        $customer = User::factory()->create(['type' => 'customer', 'is_admin' => false, 'email_verified_at' => now()]);
        $this->actingAs($customer)->get(app(PublicityEventAccess::class)->link($event))->assertRedirect(route('publicity.join.complete'));
        $this->get(route('publicity.join.complete'))->assertRedirect(route('customer.media.index'));
        $this->assertDatabaseHas('customer_studios', ['user_id' => $customer->id, 'studio_id' => $studio->id]);
        $this->assertDatabaseCount('concert_access_grants', 0);
        $this->assertDatabaseCount('order_media_entitlements', 0);
    }

    public function test_qr_waits_for_email_verification_and_resumes_after_verification(): void
    {
        $f = $this->fixture();
        app(SlideshowManagement::class)->saveSettings($f['event'], ['enabled' => true, 'photo_seconds' => 2, 'promotion_every' => 5]);
        CompetitionGallerySetting::create(['scheduling_event_id' => $f['event']->id, 'login_code_hash' => bcrypt('EXAMPLE')]);
        $customer = User::factory()->create(['type' => 'customer', 'is_admin' => false, 'email_verified_at' => null]);
        $link = app(PublicityEventAccess::class)->link($f['event']);
        $this->actingAs($customer)->get($link)->assertRedirect(route('publicity.join.complete'));
        $this->get(route('publicity.join.complete'))->assertRedirect(route('customer.verification.notice'));
        $this->assertDatabaseCount('customer_competition_registrations', 0);
        $verification = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $customer->id, 'hash' => sha1($customer->email)]);
        $this->get($verification)->assertRedirect(route('publicity.join.complete'));
        $this->get(route('publicity.join.complete'))->assertRedirect(route('customer.media.index'));
        $this->assertDatabaseCount('customer_competition_registrations', 1);
    }

    public function test_video_adverts_are_validated_scoped_and_replaceable(): void
    {
        $f = $this->fixture();
        $admin = User::factory()->staff()->create();
        $upload = fn () => new UploadedFile(base_path('tests/Fixtures/publicity/advert.mp4'), 'advert.mp4', 'video/mp4', null, true);
        $data = ['title' => 'Video advert', 'enabled' => 1, 'duration_seconds' => 5, 'sort_order' => 0, 'video' => $upload()];
        $url = route('admin.publicity.event-slides.store', $f['event']);
        $this->actingAs(User::factory()->create(['type' => 'customer', 'is_admin' => false]))->post($url, $data)->assertForbidden();
        $this->actingAs($admin)->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $slide = PublicitySlide::firstOrFail();
        $this->assertFalse($slide->play_sound);
        $this->assertSame(3, $slide->video_seconds);
        Storage::disk('local')->assertExists($slide->video_key);
        $playlist = app(SlideshowPlaylist::class)->manifest($f['event'], true);
        $video = collect($playlist['slides'])->firstWhere('id', 'slide-'.$slide->uuid);
        $this->assertSame('video', $video['kind']);
        $this->assertSame(3, $video['seconds']);
        $this->assertSame(hash_file('sha256', base_path('tests/Fixtures/publicity/advert.mp4')), $video['sha256']);
        $this->get(route('admin.publicity.video', ['schedulingEvent' => $f['event'], 'key' => $video['id']]))->assertOk()->assertHeader('content-type', 'video/mp4');
        $other = SchedulingEvent::create(['name' => 'Another event', 'event_type' => 'competition', 'event_date' => '2026-09-23']);
        $this->get(route('admin.publicity.video', ['schedulingEvent' => $other, 'key' => $video['id']]))->assertNotFound();
        $this->get(route('admin.publicity.image', ['schedulingEvent' => $f['event'], 'key' => $video['id']]))->assertNotFound();
        $this->post($url, $data + ['image' => UploadedFile::fake()->image('conflict.jpg')])->assertSessionHasErrors(['image', 'video']);
        $bad = array_replace($data, ['video' => UploadedFile::fake()->createWithContent('bad.mp4', 'not a video')]);
        $this->post($url, $bad)->assertSessionHasErrors('video');
        $replacement = ['title' => 'Replacement image', 'enabled' => 1, 'sort_order' => 0, 'image' => UploadedFile::fake()->image('replace.jpg')];
        $this->put(route('admin.publicity.slides.update', $slide), $replacement)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($slide->fresh()->video_key);
        $this->assertNotNull($slide->fresh()->image_key);
    }

    public function test_screen_video_download_requires_live_event_access_and_hidden_videos_are_removed(): void
    {
        $f = $this->fixture();
        $admin = User::factory()->staff()->create();
        $management = app(SlideshowManagement::class);
        $management->saveSettings($f['event'], ['enabled' => true, 'photo_seconds' => 2, 'promotion_every' => 5]);
        $management->saveSlide(null, ['title' => 'Standard video', 'audience' => 'competition', 'enabled' => true, 'sort_order' => 0,
            'video' => new UploadedFile(base_path('tests/Fixtures/publicity/advert.mp4'), 'advert.mp4', 'video/mp4', null, true)], $admin);
        $slide = PublicitySlide::firstOrFail();
        $pair = $management->beginPairing();
        $management->pair($f['event'], ['code' => $pair['code'], 'name' => 'Test TV'], $admin);
        $url = '/api/publicity/screens/videos/slide-'.$slide->uuid;
        $this->getJson($url)->assertUnauthorized();
        $this->withToken($pair['token'])->get($url)->assertOk()->assertHeader('content-type', 'video/mp4');
        $slide->update(['enabled' => false]);
        $this->withToken($pair['token'])->get($url)->assertNotFound();
        $this->assertFalse(collect(app(SlideshowPlaylist::class)->manifest($f['event'])['slides'])->contains('kind', 'video'));
        $slide->update(['enabled' => true]);
        $management->saveSettings($f['event'], ['enabled' => false]);
        $this->withToken($pair['token'])->get($url)->assertForbidden();
    }

    public function test_standard_advert_library_is_shared_and_versions_follow_media_content(): void
    {
        $f = $this->fixture();
        $admin = User::factory()->staff()->create();
        $management = app(SlideshowManagement::class);
        $playlist = app(SlideshowPlaylist::class);
        $management->saveSlide(null, ['title' => 'General photo', 'audience' => 'competition', 'enabled' => true,
            'image' => UploadedFile::fake()->image('general.jpg')], $admin);
        $management->saveSlide(null, ['title' => 'General video', 'audience' => 'competition', 'enabled' => true,
            'video' => new UploadedFile(base_path('tests/Fixtures/publicity/advert.mp4'), 'advert.mp4', 'video/mp4', null, true)], $admin);
        $management->saveSlide($f['event'], ['title' => 'Event only', 'enabled' => true, 'body' => 'Welcome'], $admin);
        $management->saveSlide(null, ['title' => 'Concert only', 'audience' => 'concert', 'enabled' => true, 'body' => 'Concert'], $admin);
        $first = $playlist->manifest($f['event'], true);
        $this->assertCount(2, $first['standard_slides']);
        $this->assertSame(['image', 'video'], array_column($first['standard_slides'], 'kind'));
        $other = SchedulingEvent::create(['name' => 'Next competition', 'event_type' => 'competition', 'event_date' => '2026-09-23']);
        $this->assertSame($first['standard_slides'], $playlist->manifest($other, true)['standard_slides']);
        $disabled = $playlist->manifest($other);
        $this->assertFalse($disabled['enabled']);
        $this->assertSame([], $disabled['slides']);
        $this->assertSame($first['standard_slides'], $disabled['standard_slides']);
        $slide = PublicitySlide::where('title', 'General photo')->firstOrFail();
        $slide->update(['title' => 'New title', 'duration_seconds' => 9]);
        $updated = $playlist->manifest($other, true)['standard_slides'][0];
        $this->assertSame($first['standard_slides'][0]['asset_version'], $updated['asset_version']);
        $this->assertNotSame($first['standard_slides'][0]['version'], $updated['version']);
        $slide->update(['image_hash' => str_repeat('f', 64)]);
        $this->assertNotSame($updated['asset_version'], $playlist->manifest($other, true)['standard_slides'][0]['asset_version']);
        $slide->update(['enabled' => false]);
        $this->assertCount(1, $playlist->manifest($other, true)['standard_slides']);
    }

    public function test_videos_are_spread_through_photos_separately_from_regular_adverts(): void
    {
        $f = $this->fixture();
        for ($i = 1; $i < 35; $i++) {
            $this->photo($f['gallery'], $f['contribution'], 'photo-'.$i.'.jpg');
        }
        $actor = User::factory()->staff()->create();
        for ($i = 0; $i < 6; $i++) {
            PublicitySlide::create(['created_by' => $actor->id, 'title' => 'Video '.$i, 'audience' => 'competition', 'enabled' => true,
                'sort_order' => $i, 'video_key' => 'test-'.$i.'.mp4', 'video_hash' => hash('sha256', (string) $i), 'video_seconds' => 30]);
        }
        $playlist = app(SlideshowPlaylist::class);
        $slides = $playlist->manifest($f['event'], true)['slides'];
        $photos = 0;
        $positions = [];
        $imageAdPositions = [];
        foreach ($slides as $slide) {
            if (str_starts_with($slide['id'], 'photo-')) {
                $photos++;
            } elseif ($slide['kind'] === 'video') {
                $positions[] = $photos;
            } else {
                $imageAdPositions[] = $photos;
            }
        }
        $this->assertSame(35, $photos);
        $this->assertSame([3, 9, 14, 21, 27, 33], $positions);
        $this->assertSame([0, 5, 10, 15, 20, 25, 30], $imageAdPositions);
        $this->assertCount(6, array_unique(array_column(array_filter($slides, fn ($s) => $s['kind'] === 'video'), 'id')));
        $this->assertStringStartsWith('photo-', $slides[array_key_last($slides)]['id']);

        // Growing galleries recalculate the spacing, without duplicating videos.
        for ($i = 35; $i < 60; $i++) {
            $this->photo($f['gallery'], $f['contribution'], 'photo-'.$i.'.jpg');
        }
        $photos = 0;
        $positions = [];
        foreach ($playlist->manifest($f['event'], true)['slides'] as $slide) {
            if (str_starts_with($slide['id'], 'photo-')) {
                $photos++;
            }
            if ($slide['kind'] === 'video') {
                $positions[] = $photos;
            }
        }
        $this->assertSame([6, 16, 26, 36, 46, 56], $positions);

        // No photos or fewer photos than videos still include every advert.
        ProgrammePhotoFile::where('programme_photo_gallery_id', $f['gallery']->id)->update(['is_top_pic' => false]);
        $empty = $playlist->manifest($f['event'], true)['slides'];
        $this->assertCount(6, array_filter($empty, fn ($s) => $s['kind'] === 'video'));
        $f['photo']->refresh()->update(['is_top_pic' => true]);
        $few = $playlist->manifest($f['event'], true)['slides'];
        $this->assertCount(6, array_filter($few, fn ($s) => $s['kind'] === 'video'));
        $this->assertCount(1, array_filter($few, fn ($s) => str_starts_with($s['id'], 'photo-')));
    }
}
