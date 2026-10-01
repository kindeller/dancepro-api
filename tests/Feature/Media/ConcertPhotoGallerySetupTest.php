<?php

namespace Tests\Feature\Media;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CustomerConcertGallery;
use App\Features\Customers\Services\GuestGalleryCatalogue;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\AdminTopPics;
use App\Features\Media\Services\ConcertPhotoGallerySetup;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcertPhotoGallerySetupTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create();
        $event = SchedulingEvent::create([
            'studio_id' => $studio->id, 'name' => 'Spring concert', 'event_type' => 'concert',
            'event_date' => '2026-09-22',
        ]);
        $shift = $event->shifts()->create(['shift_date' => '2026-09-22']);
        $booking = ConcertBooking::create([
            'studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'director@example.test',
            'contact_phone' => '0400000000', 'status' => 'pending',
        ]);
        $bookingItem = ConcertBookingItem::create([
            'concert_booking_id' => $booking->id, 'item_type' => 'concert', 'title' => 'Spring concert',
            'venue_name' => 'Theatre', 'venue_address' => 'Example street', 'starts_at' => '18:00',
            'finishes_at' => '20:00', 'event_date' => '2026-09-22', 'scheduling_event_id' => $event->id,
        ]);
        $concert = Concert::factory()->published()->create([
            'studio_id' => $studio->id, 'concert_booking_item_id' => $bookingItem->id, 'name' => 'Spring concert',
        ]);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $itemUuid = (string) Str::uuid();
        $snapshot = ['event_type' => 'concert', 'nodes' => [[
            'uuid' => $itemUuid, 'kind' => 'item', 'number' => '1', 'title' => 'Opening Dance',
            'folder_number' => '01', 'folder_title' => 'Opening Dance',
        ]]];
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        $plan = ProgrammeUploadPlan::create([
            'programme_id' => $programme->id, 'programme_revision_id' => $revision->id,
            'digest' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot,
        ]);
        $gallery = ProgrammePhotoGallery::create([
            'scheduling_event_id' => $event->id, 'programme_upload_plan_id' => $plan->id,
        ]);
        $role = CrewRole::where('code', 'concert-photographer-p1')->firstOrFail();
        $contribution = ProgrammePhotoContribution::create([
            'programme_upload_plan_id' => $plan->id, 'item_uuid' => $itemUuid,
            'crew_role_id' => $role->id, 'assignment_uuids' => [$shift->uuid], 'status' => 'complete', 'completed_at' => now(),
        ]);
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 80, 120));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        unset($image);
        $storageKey = 'concert-source/'.Str::uuid().'/photo.jpg';
        Storage::disk('local')->put($storageKey, $bytes);
        $photo = ProgrammePhotoFile::create([
            'programme_photo_contribution_id' => $contribution->id, 'programme_photo_gallery_id' => $gallery->id,
            'client_photo_uuid' => (string) Str::uuid(), 'file_name' => 'photo.jpg', 'byte_count' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'status' => 'verified', 'verified_at' => now(),
            'storage_disk' => 'local', 'storage_key' => $storageKey, 'is_top_pic' => true,
        ]);

        return compact('staff', 'studio', 'event', 'concert', 'gallery', 'itemUuid', 'photo');
    }

    public function test_verified_uploader_photos_sync_into_private_customer_albums_idempotently(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $this->actingAs($f['staff']);
        $page = route('admin.concert-photo-gallery.show', $f['event']);
        $sync = route('admin.concert-photo-gallery.sync', $f['event']);

        $this->get(route('admin.concert-photo-gallery.events'))->assertOk()->assertSee($f['studio']->name);
        $this->get(route('admin.concert-photo-gallery.events', ['studio' => $f['studio']->uuid]))->assertOk()->assertSee('Spring concert');
        $this->get($page)->assertOk()->assertSee('Opening Dance')->assertSee('1 verified photo');
        $this->post($sync)->assertRedirect();

        $album = MediaCollection::where('concert_id', $f['concert']->id)->firstOrFail();
        $this->assertSame('01 Opening Dance', $album->name);
        $this->assertSame('draft', $album->status->value);
        $this->assertSame('private', $album->visibility->value);
        $this->assertNull($album->published_at);
        $this->assertCount(1, $album->assets);
        $this->assertSame($f['photo']->uuid, $album->assets->first()->metadata['programme_photo_file_uuid']);
        $this->assertTrue($album->assets->first()->metadata['is_top_pic']);
        $this->assertSame($album->assets->first()->id, $album->cover_media_asset_id);

        $this->get(route('admin.concert-photo-gallery.asset', [
            'schedulingEvent' => $f['event'], 'asset' => $album->assets->first(), 'size' => 'thumbnail',
        ]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->post(route('admin.concert-photo-gallery.albums.update', [$f['event'], $album]), [
            'name' => 'Opening Number', 'sort_order' => 4,
        ])->assertRedirect();
        $this->post($sync)->assertRedirect();
        $this->assertSame('Opening Number', $album->fresh()->name);
        $this->assertSame(4, $album->fresh()->sort_order);
        $this->assertDatabaseCount('media_collections', 1);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_admin_can_open_only_the_selected_events_photo_albums(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        app(ConcertPhotoGallerySetup::class)->sync($fixture['event']);
        $album = $fixture['concert']->mediaCollections()->firstOrFail();
        $photo = $album->assets()->firstOrFail();
        $url = route('admin.concert-photo-gallery.show', ['schedulingEvent' => $fixture['event'], 'album' => $album->uuid]);
        $this->actingAs($fixture['staff'])->get(route('admin.concert-photo-gallery.show', $fixture['event']))
            ->assertOk()->assertSee($url);
        $this->get($url)->assertOk()->assertSee($album->name)->assertSee('Back to event albums')
            ->assertSee(route('admin.concert-photo-gallery.asset', ['schedulingEvent' => $fixture['event'], 'asset' => $photo, 'size' => 'thumbnail']), false);
        $other = $this->fixture();
        $this->get(route('admin.concert-photo-gallery.show', ['schedulingEvent' => $other['event'], 'album' => $album->uuid]))->assertNotFound();
        $this->get(route('admin.concert-photo-gallery.show', ['schedulingEvent' => $fixture['event'], 'album' => (string) Str::uuid()]))->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->get($url)->assertForbidden();
    }

    public function test_automatic_sync_preserves_album_edits_and_hides_late_photos_in_released_albums(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $setup = app(ConcertPhotoGallerySetup::class);
        $setup->syncVerifiedPhoto($f['event'], $f['photo']);
        $album = MediaCollection::where('concert_id', $f['concert']->id)->sole();
        $album->update(['name' => 'Edited title', 'sort_order' => 9, 'status' => 'published',
            'visibility' => 'customer', 'published_at' => now()]);
        $late = $f['photo']->replicate(['uuid', 'client_photo_uuid']);
        $late->uuid = (string) Str::uuid();
        $late->client_photo_uuid = (string) Str::uuid();
        $late->storage_key = 'concert-source/'.$late->uuid.'/late.jpg';
        Storage::disk('local')->copy($f['photo']->storage_key, $late->storage_key);
        $late->save();
        $setup->syncVerifiedPhoto($f['event'], $late);
        $album->refresh();
        $this->assertSame('Edited title', $album->name);
        $this->assertSame(9, $album->sort_order);
        $this->assertSame('customer', $album->visibility->value);
        $this->assertSame(2, $album->assets()->count());
        $this->assertSame(1, $album->assets()->where('is_visible', true)->count());
        $this->assertFalse($album->assets()->where('metadata->programme_photo_file_uuid', $late->uuid)->sole()->is_visible);
        $setup->sync($f['event']);
        $this->assertSame(1, $album->assets()->where('is_visible', true)->count());
        $original = $album->assets()->where('is_visible', true)->sole();
        $original->update(['status' => 'archived', 'display_name' => 'Edited caption']);
        $setup->syncVerifiedPhoto($f['event'], $f['photo']);
        $this->assertSame('archived', $original->fresh()->status->value);
        $this->assertSame('Edited caption', $original->fresh()->display_name);
        $original->delete();
        $setup->syncVerifiedPhoto($f['event'], $f['photo']);
        $this->assertSoftDeleted('media_assets', ['id' => $original->id]);
    }

    public function test_unlinked_events_and_non_admin_users_cannot_create_customer_albums(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $f['concert']->update(['concert_booking_item_id' => null]);
        $this->actingAs($f['staff'])->post(route('admin.concert-photo-gallery.sync', $f['event']))->assertConflict();
        $this->assertDatabaseCount('media_collections', 0);

        $this->actingAs(User::factory()->create(['type' => 'crew', 'is_admin' => false]))
            ->get(route('admin.concert-photo-gallery.events'))->assertForbidden();
    }

    public function test_tracker_orders_concerts_by_date_or_studio_and_uses_source_statuses(): void
    {
        $staff = User::factory()->staff()->create();
        $zulu = Studio::factory()->create(['name' => 'Zulu Dance']);
        $alpha = Studio::factory()->create(['name' => 'Alpha Dance']);
        SchedulingEvent::create([
            'studio_id' => $zulu->id, 'name' => 'Early concert', 'event_type' => 'concert',
            'event_date' => '2026-02-01',
        ]);
        SchedulingEvent::create([
            'studio_id' => $alpha->id, 'name' => 'Later concert', 'event_type' => 'concert',
            'event_date' => '2026-11-01',
        ]);

        $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['view' => 'date', 'year' => 2026, 'phase' => 'completion']))
            ->assertOk()->assertSeeInOrder(['Early concert', 'Later concert'])
            ->assertSee('Pending')->assertDontSee('Not invoiced')->assertSee('Not released');
        $this->get(route('admin.concert-tracker.index', ['view' => 'studio', 'year' => 2026, 'phase' => 'completion']))
            ->assertOk()->assertSeeInOrder(['Alpha Dance', 'Later concert', 'Zulu Dance', 'Early concert']);
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.concert-tracker.index', ['year' => 2026]))->assertForbidden();
    }

    public function test_release_group_publishes_the_album_to_verified_studio_customers(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $this->actingAs($f['staff'])->post(route('admin.concert-photo-gallery.sync', $f['event']))->assertRedirect();
        $album = MediaCollection::where('concert_id', $f['concert']->id)->firstOrFail();
        $customer = User::factory()->customer()->create();
        CustomerStudio::create([
            'user_id' => $customer->id, 'studio_id' => $f['studio']->id, 'access_verified_at' => now(),
        ]);

        $this->actingAs($customer)->get(route('customer.concerts.photos.show', $f['concert']))
            ->assertOk()->assertDontSee('01 Opening Dance');
        $this->actingAs($f['staff'])->post(route('admin.concert-photo-releases.store'), [
            'name' => 'Spring concert photos', 'collection_uuids' => [$album->uuid],
        ])->assertRedirect();
        $group = ConcertPhotoReleaseGroup::firstOrFail();
        $this->assertSame($album->assets->first()->id, $group->cover_media_asset_id);
        $this->assertDatabaseHas('concert_photo_release_group_changes', ['action' => 'created']);
        $this->get(route('admin.concert-photo-releases.index'))
            ->assertRedirect(route('admin.concert-tracker.index', ['year' => now()->year]));
        $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'completion']))->assertOk()
            ->assertSee('Photo release')->assertDontSee('Choose parent album cover');
        $this->get(route('admin.concert-tracker.tasks.show', [$f['event'], 'photo_release']))->assertOk()
            ->assertSee('Photo release')
            ->assertSee('Release photos together')->assertSee('Select all ready')->assertDontSee('Choose parent album cover');

        $this->post(route('admin.concert-photo-releases.release', $group))->assertRedirect();
        $album->refresh();
        $this->assertSame('published', $album->status->value);
        $this->assertSame('customer', $album->visibility->value);
        $this->assertNotNull($album->published_at);
        $this->assertDatabaseHas('concert_photo_release_group_changes', ['action' => 'released']);
        $this->get(route('admin.concert-tracker.index', ['view' => 'studio', 'year' => 2026, 'phase' => 'completion']))
            ->assertOk()->assertSee('Studio view')->assertSee('Released');
        $this->get(route('admin.concert-tracker.tasks.show', [$f['event'], 'photo_release']))
            ->assertOk()->assertSee('Spring concert photos')->assertSee('Released');
        $this->actingAs($customer)->get(route('customer.concerts.photos.show', $f['concert']))
            ->assertOk()->assertSee('01 Opening Dance')->assertDontSee('group='.$group->uuid, false);
        $this->actingAs($customer)->get(route('customer.concerts.photos.show', [
            'concert' => $f['concert'], 'group' => $group->uuid,
        ]))->assertOk()->assertSee('01 Opening Dance')->assertSee('cover');
        $this->actingAs($customer)->get(route('customer.concerts.photos.show', [
            'concert' => $f['concert'], 'group' => $group->uuid, 'folder' => $album->uuid,
        ]))->assertOk()->assertSee('Watermarked preview');
        $this->get(route('guest-galleries.concerts.cover', [
            'concert' => $f['concert'], 'group' => $group,
        ]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('guest-galleries.studios.show', $f['studio']))
            ->assertRedirect(route('guest-galleries.studios.access', $f['studio']));
        $this->post(route('guest-galleries.studios.unlock', $f['studio']), [
            'email' => 'guest@example.test', 'login_code' => $f['studio']->code,
        ])->assertRedirect(route('guest-galleries.studios.show', $f['studio']));
        $this->get(route('guest-galleries.studios.show', $f['concert']->studio))->assertOk()
            ->assertSee('Spring concert')->assertDontSee('Spring concert photos')
            ->assertSee(route('guest-galleries.concerts.show', ['concert' => $f['concert']]), false);

        $asset = $album->assets->first();
        $this->actingAs($f['staff'])->post(route('admin.concert-photo-releases.cover.update', $group), [
            'asset_uuid' => $asset->uuid,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.concert-photo-gallery.albums.cover.update', [$f['event'], $album]), [
            'asset_uuid' => $asset->uuid,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('concert_photo_release_group_changes', ['action' => 'cover_updated']);
        $this->assertDatabaseHas('concert_photo_release_group_changes', ['action' => 'album_cover_updated']);
    }

    public function test_event_release_includes_all_albums_and_sends_one_combined_announcement(): void
    {
        Storage::fake('local');
        Queue::fake();
        $first = $this->fixture();
        $second = $this->fixture();
        $second['event']->update(['studio_id' => $first['studio']->id]);
        $second['concert']->update(['studio_id' => $first['studio']->id, 'name' => 'Senior Concert']);
        foreach ([$first, $second] as $fixture) {
            $fixture['concert']->bookingItem->update(['approval_status' => 'approved']);
            $fixture['concert']->update(['status' => 'draft', 'is_enabled' => false, 'requires_approval' => true, 'approved_at' => null]);
            app(ConcertPhotoGallerySetup::class)->sync($fixture['event'], $first['staff']);
        }
        $album = MediaCollection::where('concert_id', $first['concert']->id)->firstOrFail();
        $extra = $album->replicate();
        $extra->fill(['uuid' => (string) Str::uuid(), 'name' => 'Finale'])->save();
        $asset = $album->assets()->firstOrFail()->replicate();
        $extraKey = 'concert-source/'.Str::uuid().'/finale.jpg';
        Storage::disk('local')->copy($asset->storage_key, $extraKey);
        $asset->fill(['uuid' => (string) Str::uuid(), 'media_collection_id' => $extra->id, 'storage_key' => $extraKey])->save();
        $this->actingAs($first['staff'])->post(route('admin.concert-photo-releases.store'), [
            'name' => 'Combined announcement', 'event_uuids' => [$first['event']->uuid, $second['event']->uuid],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $group = ConcertPhotoReleaseGroup::sole();
        $this->assertCount(3, $group->collections);
        $this->assertNotNull($group->released_at);
        foreach ([$first, $second] as $fixture) {
            $this->assertTrue($fixture['concert']->fresh()->isPhotoGalleryAvailable());
            $this->assertFalse($fixture['concert']->fresh()->isPubliclyAvailable()); // Video still needs director release.
        }
        $cards = app(GuestGalleryCatalogue::class)->concerts($first['studio']);
        $this->assertCount(2, $cards);
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $first['studio']->id, 'access_verified_at' => now()]);
        $this->actingAs($customer)->get(route('customer.concerts.photos.show', $first['concert']))
            ->assertOk()->assertSee('01 Opening Dance')->assertSee('Finale')->assertDontSee('Combined announcement')->assertDontSee('Senior Concert');
        $email = route('admin.concert-photo-releases.email.send', $group);
        $this->actingAs($first['staff'])->post($email, ['confirm_send' => true])->assertSessionHasNoErrors();
        $this->post($email, ['confirm_send' => true])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::where('photo_release_group_id', $group->id)->sole();
        $this->assertSame($customer->email, $delivery->recipient_email);
        $this->assertStringContainsString('Spring concert', $delivery->body);
        $this->assertStringContainsString('Senior Concert', $delivery->body);
    }

    public function test_event_release_rejects_mixed_studios_and_unfinished_uploads_without_partial_release(): void
    {
        Storage::fake('local');
        $first = $this->fixture();
        $second = $this->fixture();
        foreach ([$first, $second] as $fixture) {
            $fixture['concert']->bookingItem->update(['approval_status' => 'approved']);
            app(ConcertPhotoGallerySetup::class)->sync($fixture['event'], $first['staff']);
        }
        $payload = ['name' => 'Cannot release', 'event_uuids' => [$first['event']->uuid, $second['event']->uuid]];
        $this->actingAs($first['staff'])->post(route('admin.concert-photo-releases.store'), $payload)->assertSessionHasErrors('event_uuids');
        $second['event']->update(['studio_id' => $first['studio']->id]);
        $second['concert']->update(['studio_id' => $first['studio']->id]);
        $second['photo']->contribution->update(['status' => 'uploading', 'completed_at' => null]);
        $this->post(route('admin.concert-photo-releases.store'), $payload)->assertSessionHasErrors('event_uuids');
        $this->assertDatabaseCount('concert_photo_release_groups', 0);
        $this->assertSame(0, MediaCollection::whereNotNull('published_at')->count());
    }

    public function test_event_cards_use_their_own_selected_top_pic_and_date_in_a_shared_release(): void
    {
        Storage::fake('local');
        $first = $this->fixture();
        $second = $this->fixture();
        $third = $this->fixture();
        $events = [$first, $second, $third];
        foreach ($events as $index => $fixture) {
            $fixture['event']->update(['studio_id' => $first['studio']->id]);
            $fixture['concert']->update(['studio_id' => $first['studio']->id, 'event_date' => '2026-09-'.(20 + $index)]);
            app(ConcertPhotoGallerySetup::class)->sync($fixture['event']);
        }
        $selected = $second['photo']->replicate(['uuid', 'client_photo_uuid']);
        $selected->uuid = (string) Str::uuid();
        $selected->client_photo_uuid = (string) Str::uuid();
        $selected->storage_key = 'concert-source/'.$selected->uuid.'/photo.jpg';
        Storage::disk('local')->copy($second['photo']->storage_key, $selected->storage_key);
        $selected->save();
        app(ConcertPhotoGallerySetup::class)->sync($second['event']);
        app(AdminTopPics::class)->chooseCover($second['event'], $selected->uuid, $first['staff']);
        $this->actingAs($first['staff'])->post(route('admin.concert-photo-releases.store'), [
            'name' => 'Shared studio release', 'collection_uuids' => MediaCollection::pluck('uuid')->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $group = ConcertPhotoReleaseGroup::firstOrFail();
        $this->post(route('admin.concert-photo-releases.release', $group))->assertRedirect();
        $gallery = app(CustomerConcertGallery::class);
        $covers = collect($events)->map(fn ($fixture) => $gallery->parentCoverAsset($fixture['concert']));
        $this->assertCount(3, $covers->pluck('id')->unique());
        foreach ($events as $index => $fixture) {
            $this->assertSame($fixture['concert']->id, $covers[$index]->collection->concert_id);
        }
        $this->assertSame($selected->uuid, $covers[1]->metadata['programme_photo_file_uuid']);
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $first['studio']->id, 'access_verified_at' => now()]);
        $page = $this->actingAs($customer)->get(route('customer.media.index'))->assertOk();
        foreach ($events as $index => $fixture) {
            $page->assertSee($fixture['concert']->event_date->format('j M Y'))
                ->assertSee(route('customer.concerts.photos.image', ['concert' => $fixture['concert'], 'asset' => $covers[$index]->uuid, 'size' => 'cover']), false);
        }
        $this->get(route('customer.profile.studio', $first['studio']))->assertOk()
            ->assertSee('gallery-navigation-grid')->assertSee('gallery-nav-image')->assertSee('20 Sep 2026');
        $this->get(route('customer.concerts.photos.show', $second['concert']))->assertOk()
            ->assertSee('gallery-navigation-grid concert-albums')->assertSee('gallery-nav-footer')
            ->assertSee('2 photos')->assertSee('View album');
        $guestCards = app(GuestGalleryCatalogue::class)->concerts($first['studio'])->keyBy('concert.id');
        foreach ($events as $index => $fixture) {
            $this->assertSame(route('guest-galleries.concerts.image', [
                'concert' => $fixture['concert'], 'asset' => $covers[$index]->uuid, 'size' => 'cover',
            ]), $guestCards[$fixture['concert']->id]['image']);
        }
        // A hidden selection must never become a customer card cover.
        $covers[1]->update(['is_visible' => false]);
        $fallback = $gallery->parentCoverAsset($second['concert']);
        $this->assertSame($second['photo']->uuid, $fallback->metadata['programme_photo_file_uuid']);
        $this->assertSame($second['concert']->id, $fallback->collection->concert_id);
    }

    public function test_parent_album_can_contain_routine_albums_from_multiple_concerts(): void
    {
        Storage::fake('local');
        $first = $this->fixture();
        $second = $this->fixture();
        $second['event']->update(['studio_id' => $first['studio']->id, 'name' => 'Winter concert']);
        $second['concert']->update(['studio_id' => $first['studio']->id, 'name' => 'Winter concert']);
        $this->actingAs($first['staff']);
        $this->post(route('admin.concert-photo-gallery.sync', $first['event']))->assertRedirect();
        $this->post(route('admin.concert-photo-gallery.sync', $second['event']))->assertRedirect();
        $albums = MediaCollection::query()->orderBy('id')->get();
        $albums->last()->update(['name' => 'Winter finale']);

        $this->post(route('admin.concert-photo-releases.store'), [
            'name' => 'Dance school 2026',
            'collection_uuids' => $albums->pluck('uuid')->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $group = ConcertPhotoReleaseGroup::firstOrFail();
        $this->post(route('admin.concert-photo-releases.release', $group))->assertRedirect();

        $customer = User::factory()->customer()->create();
        CustomerStudio::create([
            'user_id' => $customer->id,
            'studio_id' => $first['studio']->id,
            'access_verified_at' => now(),
        ]);
        $this->actingAs($customer)->get(route('customer.concerts.photos.show', [
            'concert' => $first['concert'],
            'group' => $group->uuid,
        ]))->assertOk()->assertSee('01 Opening Dance')->assertSee('Winter finale')->assertSee('Winter concert');

        $winterPhoto = $albums->last()->assets()->firstOrFail();
        $this->post(route('customer.concerts.photos.selections.store', $first['concert']), [
            'asset_uuid' => $winterPhoto->uuid,
            'action' => 'add',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_concert_photo_selections', [
            'user_id' => $customer->id,
            'concert_id' => $second['concert']->id,
            'media_asset_id' => $winterPhoto->id,
            'in_cart' => true,
        ]);
    }
}
