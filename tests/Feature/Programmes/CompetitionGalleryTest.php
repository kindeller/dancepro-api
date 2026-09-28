<?php

namespace Tests\Feature\Programmes;

use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CompetitionOrderCatalogue;
use App\Features\Programmes\Models\CompetitionProgrammeAddition;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CompetitionGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $multiDay = false): array
    {
        $user = User::factory()->staff()->create();
        $this->actingAs($user);
        $event = SchedulingEvent::create(['name' => 'Gallery test', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $day = (string) Str::uuid();
        $section = (string) Str::uuid();
        $item = (string) Str::uuid();
        $nodes = [
            ['uuid' => $day, 'kind' => 'day', 'date' => '2026-09-04', 'title' => 'Friday'],
            ['uuid' => $section, 'kind' => 'section', 'number' => '7', 'title' => 'Jazz 12 & Under', 'parent_uuid' => $day],
            ['uuid' => $item, 'kind' => 'item', 'number' => '3', 'title' => 'Jessica Smith', 'parent_uuid' => $section],
        ];
        if ($multiDay) {
            $nodes[] = ['uuid' => (string) Str::uuid(), 'kind' => 'day', 'date' => '2026-09-05', 'title' => 'Saturday'];
        }
        $snapshot = ['event_type' => 'competition', 'event_code' => 'EDC926', 'nodes' => $nodes];
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        $plan = ProgrammeUploadPlan::create(['programme_id' => $programme->id, 'programme_revision_id' => $revision->id, 'digest' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot]);
        $gallery = ProgrammePhotoGallery::create(['scheduling_event_id' => $event->id, 'programme_upload_plan_id' => $plan->id]);

        return compact('user', 'event', 'programme', 'revision', 'plan', 'gallery', 'day', 'section', 'item');
    }

    private function url(array $f, ?string $folder = null): string
    {
        return route('admin.competition-gallery.show', ['schedulingEvent' => $f['event'], 'folder' => $folder]);
    }

    private function photo(array $f, string $roleCode, array $attributes = []): ProgrammePhotoFile
    {
        $role = CrewRole::where('code', $roleCode)->firstOrFail();
        $contribution = ProgrammePhotoContribution::firstOrCreate([
            'programme_upload_plan_id' => $f['plan']->id, 'item_uuid' => $f['item'], 'crew_role_id' => $role->id,
        ], ['assignment_uuids' => []]);
        $key = 'test/'.Str::uuid().'.jpg';
        $image = imagecreatetruecolor(240, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 70, 100, 140));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        unset($image);
        Storage::disk('local')->put($key, $bytes);

        return ProgrammePhotoFile::create(array_replace([
            'programme_photo_contribution_id' => $contribution->id, 'programme_photo_gallery_id' => $f['gallery']->id,
            'client_photo_uuid' => (string) Str::uuid(), 'file_name' => 'photo.jpg', 'byte_count' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
            'status' => 'verified', 'verified_at' => now(), 'storage_disk' => 'local', 'storage_key' => $key,
        ], $attributes));
    }

    public function test_media_menu_opens_competition_photos_and_links_to_galleries(): void
    {
        $f = $this->fixture();
        SchedulingEvent::create(['name' => 'Concert excluded', 'event_type' => 'concert', 'event_date' => '2026-09-04']);
        SchedulingEvent::create(['name' => 'Cancelled excluded', 'event_type' => 'competition', 'event_date' => '2026-09-04', 'cancelled_at' => now()]);
        $this->get(route('admin.competition-gallery.index'))->assertOk()->assertSee('Competition photos')
            ->assertSee('Gallery test')->assertSee($this->url($f), false)->assertDontSee('Concert excluded')->assertDontSee('Cancelled excluded');
        $this->get($this->url($f))->assertOk()->assertSee(route('admin.competition-gallery.index'), false);
        $this->actingAs(User::factory()->create(['type' => 'crew', 'is_admin' => false]));
        $this->get(route('admin.competition-gallery.index'))->assertForbidden();
    }

    public function test_gallery_title_is_independent_audited_and_protected_from_stale_updates(): void
    {
        $f = $this->fixture();
        $url = route('admin.competition-gallery.settings.update', $f['event']);
        $this->get(route('admin.competition-gallery.settings', $f['event']))->assertOk()->assertSee('Gallery title');
        $this->post($url, ['action' => 'title', 'version' => 0, 'display_title' => '  Spring Dance Photos  '])->assertRedirect();
        $this->assertSame('Gallery test', $f['event']->fresh()->name);
        $this->assertEquals($f['plan']->snapshot, $f['plan']->fresh()->snapshot);
        $this->get(route('admin.competition-gallery.index'))->assertOk()->assertSee('Spring Dance Photos');
        $this->get($this->url($f))->assertOk()->assertSee('Spring Dance Photos');
        $this->assertDatabaseHas('competition_gallery_setting_changes', ['scheduling_event_id' => $f['event']->id, 'actor_user_id' => $f['user']->id]);
        $this->post($url, ['action' => 'title', 'version' => 0, 'display_title' => 'Stale title'])->assertConflict();
        $this->post($url, ['action' => 'title', 'version' => 1, 'display_title' => 'Spring Dance Photos'])->assertRedirect();
        $this->assertDatabaseCount('competition_gallery_setting_changes', 1);
        $this->post($url, ['action' => 'title', 'version' => 1, 'display_title' => '   '])->assertRedirect();
        $this->get($this->url($f))->assertOk()->assertDontSee('Spring Dance Photos')->assertSee('Gallery test');
        $this->assertSame('private', $f['gallery']->fresh()->visibility);
    }

    public function test_gallery_cover_uses_only_own_verified_photo_while_event_list_keeps_the_event_logo(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $other = $this->fixture();
        $foreign = $this->photo($other, 'competition-photographer-p1');
        $url = route('admin.competition-gallery.settings.update', $f['event']);
        $this->postJson($url, ['action' => 'cover', 'version' => 0, 'photo_uuid' => $foreign->uuid])->assertUnprocessable();
        $this->assertDatabaseCount('competition_gallery_settings', 0);
        $photo->update(['status' => 'reserved']);
        $this->postJson($url, ['action' => 'cover', 'version' => 0, 'photo_uuid' => $photo->uuid])->assertUnprocessable();
        $photo->update(['status' => 'verified']);
        $this->post($url, ['action' => 'cover', 'version' => 0, 'photo_uuid' => $photo->uuid])->assertRedirect();
        $this->assertDatabaseHas('competition_gallery_settings', ['scheduling_event_id' => $f['event']->id, 'cover_photo_uuid' => $photo->uuid]);
        $coverUrl = route('admin.competition-gallery.image', ['schedulingEvent' => $f['event'], 'photo' => $photo->uuid, 'size' => 'thumbnail']);
        $this->get(route('admin.competition-gallery.index'))->assertOk()->assertDontSee($coverUrl, false)->assertDontSee($photo->storage_key);
        $this->get(route('admin.competition-gallery.settings', $f['event']))->assertOk()->assertSee($coverUrl, false);
        $this->get($this->url($f, $f['item']))->assertOk()->assertSee('Current cover');
        $photo->update(['status' => 'reserved']);
        $this->get(route('admin.competition-gallery.settings', $f['event']))->assertOk()->assertDontSee($coverUrl, false);
        $photo->update(['status' => 'verified']);
        $this->post($url, ['action' => 'reset_cover', 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('competition_gallery_settings', ['scheduling_event_id' => $f['event']->id, 'cover_photo_uuid' => null]);
        $this->assertSame('verified', $photo->fresh()->status);
    }

    public function test_backend_folder_cards_use_the_same_static_photo_covers_as_customer_galleries(): void
    {
        Storage::fake('local');
        $f = $this->fixture(true);
        $photo = $this->photo($f, 'competition-photographer-p1', ['is_top_pic' => true]);
        $thumbnail = route('admin.competition-gallery.image', [
            'schedulingEvent' => $f['event'],
            'photo' => $photo->uuid,
            'size' => 'thumbnail',
        ]);

        $this->get($this->url($f))->assertOk()->assertSee($thumbnail, false);
        $this->get($this->url($f, $f['day']))->assertOk()->assertSee($thumbnail, false);
        $this->get($this->url($f, $f['section']))->assertOk()->assertSee($thumbnail, false);
    }

    public function test_gallery_settings_require_admin_and_reject_non_competitions(): void
    {
        $f = $this->fixture();
        $url = route('admin.competition-gallery.settings.update', $f['event']);
        $this->actingAs(User::factory()->create(['type' => 'crew', 'is_admin' => false]));
        $this->post($url, ['action' => 'title', 'version' => 0, 'display_title' => 'Forbidden'])->assertForbidden();
        $this->get(route('admin.competition-gallery.settings', $f['event']))->assertForbidden();
        $this->actingAs($f['user']);
        $this->postJson($url, ['action' => 'title', 'version' => 0, 'display_title' => str_repeat('x', 256)])->assertUnprocessable();
        $f['event']->update(['event_type' => 'concert']);
        $this->post($url, ['action' => 'title', 'version' => 0, 'display_title' => 'Wrong type'])->assertConflict();
        $this->assertDatabaseCount('competition_gallery_settings', 0);
    }

    private function readyForPreview(array $f, bool $p2Complete = true): void
    {
        $f['event']->shifts()->firstOrCreate(['shift_date' => '2026-09-04', 'period' => 'morning']);
        foreach (['competition-photographer-p1', 'competition-photographer-p2'] as $index => $code) {
            $role = CrewRole::where('code', $code)->firstOrFail();
            $f['event']->roleRequirements()->firstOrCreate(['crew_role_id' => $role->id], ['quantity' => 1]);
            $complete = $index === 0 || $p2Complete;
            ProgrammePhotoContribution::updateOrCreate(['programme_upload_plan_id' => $f['plan']->id, 'item_uuid' => $f['item'], 'crew_role_id' => $role->id],
                ['assignment_uuids' => [], 'status' => $complete ? 'complete' : 'receiving', 'completed_at' => $complete ? now() : null]);
        }
    }

    public function test_registered_customer_images_remain_event_scoped_and_follow_live_readiness(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer);
        $album = route('customer.competitions.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]);
        $image = route('customer.competitions.image', ['schedulingEvent' => $f['event'], 'photo' => $photo->uuid]);
        $this->get($image)->assertForbidden();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id,
            'scheduling_event_id' => $f['event']->id, 'access_verified_at' => now()]);
        $this->get($album)->assertOk()->assertSee($image, false)->assertSee('gallery-photo-viewer')
            ->assertSee('Watermarked preview')->assertDontSee('Customer preview')->assertDontSee('/admin/scheduling-events/', false);
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->readyForPreview($f, false);
        $this->get($album)->assertNotFound();
        $this->get($image)->assertNotFound();
        $this->readyForPreview($f);
        $other = $this->fixture();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id,
            'scheduling_event_id' => $other['event']->id, 'access_verified_at' => now()]);
        $this->actingAs($customer)->get(route('customer.competitions.image', ['schedulingEvent' => $other['event'], 'photo' => $photo->uuid]))->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->get($image)->assertForbidden();
    }

    public function test_customer_can_save_photos_and_routines_but_cannot_select_unready_or_foreign_media(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $customer = User::factory()->customer()->create();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id,
            'scheduling_event_id' => $f['event']->id, 'access_verified_at' => now()]);
        $this->actingAs($customer);
        $url = route('customer.competitions.selections.store', $f['event']);
        $album = route('customer.competitions.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]);
        $this->get($album)->assertOk()->assertSee('All photos from this routine')
            ->assertSee('Photos + video package')->assertSee('Individual photos')
            ->assertSee('Individual video from this routine')->assertSee('Video available for cart when ready')->assertSee('$10')
            ->assertSee('data-start-selection="add"', false)->assertSee('data-start-selection="favourite"', false)
            ->assertSee('data-tooltip="Add to cart · $40"', false)->assertSee('data-tooltip="Add to favourites"', false)
            ->assertSee('Add to cart · $10')->assertSee('gallery-viewer-photo-frame')
            ->assertSee('data-viewer-cart', false)->assertSee('data-viewer-favourite', false)
            ->assertDontSee('data-select-action', false)->assertSee("event.key === 'Escape'", false)
            ->assertSee('Save selected to favourites');
        $input = ['product' => 'photo', 'item_uuid' => $f['item'], 'photo_uuid' => $photo->uuid];
        $globalFavourites = route('customer.media.favourites', ['focus_kind' => 'competition', 'focus_event' => $f['event']->uuid]);
        $globalCart = route('customer.media.cart', ['focus_kind' => 'competition', 'focus_event' => $f['event']->uuid]);
        $this->post($url, $input + ['action' => 'favourite'])->assertRedirect();
        $this->get($globalFavourites)->assertOk()
            ->assertSee('07 Jazz 12 & Under')->assertSee('03 Jessica Smith')
            ->assertSee('Individual photo')->assertSee('Add to cart')
            ->assertSee('event-selection-items is-favourites', false)
            ->assertSee(route('customer.competitions.image', ['schedulingEvent' => $f['event'], 'photo' => $photo->uuid, 'size' => 'thumbnail']), false);
        $this->get(route('customer.competitions.show', ['schedulingEvent' => $f['event'],
            'folder' => $f['item'], 'page' => 1, 'photo' => $photo->uuid]))->assertOk()
            ->assertSee('focusIndex >= 0', false)->assertSee($photo->uuid);
        $this->post($url, $input + ['action' => 'add'])->assertRedirect();
        $this->get($album)->assertOk()->assertSee('Remove this photo from cart')
            ->assertSee('Remove this photo from favourites');
        $this->get($globalFavourites)->assertOk()
            ->assertSee('Favourites')->assertDontSee('Event subtotal');
        $this->get($globalCart)->assertOk()
            ->assertSee('07 Jazz 12 & Under')->assertSee('03 Jessica Smith')
            ->assertSeeText('Individual photo')->assertSee('$10.00')->assertSee('aria-label="Remove from cart"', false)
            ->assertDontSee('Saved favourites');
        $this->assertDatabaseCount('customer_media_selections', 1);
        $this->post($url, ['action' => 'add', 'product' => 'routine_photos', 'item_uuid' => $f['item']])->assertRedirect();
        $this->get($globalCart)->assertOk()->assertSee('$40.00');
        $this->post($url, ['action' => 'add', 'product' => 'routine_package', 'item_uuid' => $f['item']])->assertRedirect();
        $this->get($globalCart)->assertOk()
            ->assertSeeText('Routine package')->assertSee('$45.00');

        $other = $this->fixture();
        $otherPhoto = $this->photo($other, 'competition-photographer-p1');
        $this->actingAs($customer);
        $this->post($url, array_replace($input, ['action' => 'add', 'photo_uuid' => $otherPhoto->uuid]))->assertNotFound();
        $this->readyForPreview($f, false);
        $this->post($url, $input + ['action' => 'add'])->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->post($url, $input + ['action' => 'add'])->assertForbidden();
    }

    public function test_selection_json_response_keeps_photo_and_cart_state_in_sync(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $customer = User::factory()->customer()->create();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id,
            'scheduling_event_id' => $f['event']->id, 'access_verified_at' => now()]);
        $this->actingAs($customer);
        $url = route('customer.competitions.selections.store', $f['event']);
        $input = ['product' => 'photo', 'item_uuid' => $f['item'], 'photo_uuid' => $photo->uuid];

        $this->postJson($url, $input + ['action' => 'add'])->assertOk()
            ->assertJsonPath('data.in_cart', true)->assertJsonPath('data.is_favourite', false)
            ->assertJsonPath('data.cart_count', 1)->assertJsonPath('data.favourite_count', 0)
            ->assertJsonPath('data.photo_uuid', $photo->uuid);
        $this->postJson($url, $input + ['action' => 'favourite'])->assertOk()
            ->assertJsonPath('data.in_cart', true)->assertJsonPath('data.is_favourite', true)
            ->assertJsonPath('data.cart_count', 1)->assertJsonPath('data.favourite_count', 1);
        $this->postJson($url, $input + ['action' => 'remove'])->assertOk()
            ->assertJsonPath('data.in_cart', false)->assertJsonPath('data.is_favourite', true)
            ->assertJsonPath('data.cart_count', 0)->assertJsonPath('data.favourite_count', 1);
        $this->postJson($url, $input + ['action' => 'unfavourite'])->assertOk()
            ->assertJsonPath('data.in_cart', false)->assertJsonPath('data.is_favourite', false)
            ->assertJsonPath('data.cart_count', 0)->assertJsonPath('data.favourite_count', 0);
        $this->assertDatabaseCount('customer_media_selections', 0);
    }

    public function test_customer_can_add_multiple_ready_photos_atomically_to_cart_or_favourites(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $first = $this->photo($f, 'competition-photographer-p1');
        $second = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $customer = User::factory()->customer()->create();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id,
            'scheduling_event_id' => $f['event']->id, 'access_verified_at' => now()]);
        $this->actingAs($customer);
        $url = route('customer.competitions.selections.photos.store', $f['event']);
        $batch = ['item_uuid' => $f['item'], 'photo_uuids' => [$first->uuid, $second->uuid]];

        $this->post($url, $batch + ['action' => 'add'])->assertRedirect();
        $this->assertDatabaseCount('customer_media_selections', 2);
        $this->get(route('customer.media.cart', ['focus_kind' => 'competition', 'focus_event' => $f['event']->uuid]))->assertOk()->assertSee('$20.00');
        $this->post($url, $batch + ['action' => 'favourite'])->assertRedirect();
        $this->assertSame(2, CustomerMediaSelection::where('user_id', $customer->id)
            ->where('is_favourite', true)->where('in_cart', true)->count());

        $foreign = $this->fixture();
        $foreignPhoto = $this->photo($foreign, 'competition-photographer-p1');
        $this->actingAs($customer);
        $this->post($url, ['item_uuid' => $f['item'], 'photo_uuids' => [$first->uuid, $foreignPhoto->uuid], 'action' => 'add'])
            ->assertNotFound();
        $this->assertDatabaseCount('customer_media_selections', 2);
    }

    public function test_competition_pages_share_logo_tabs_and_link_to_account_wide_favourites_and_cart(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $f['event']->update(['logo_path' => 'events/test-logo.png']);
        $studio = Studio::factory()->create(['logo_path' => 'studios/test-logo.png']);
        $customer = User::factory()->customer()->create();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id,
            'scheduling_event_id' => $f['event']->id, 'access_verified_at' => now()]);
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $studio->id,
            'access_verified_at' => now()]);
        $this->actingAs($customer);

        $album = $this->get(route('customer.competitions.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]))->assertOk();
        $album->assertSee('Competition gallery pages')->assertSee('My Dancers')
            ->assertSee('My Studios’ Routines')->assertSee('Improvisation')
            ->assertSee('Whole Event')->assertSee('Favourites')->assertSee('Cart')
            ->assertSee($studio->logoUrl(), false)->assertSee($f['event']->crewLogoUrl(), false)
            ->assertSee('href="'.route('customer.competitions.show', $f['event']).'" aria-current="page"', false);
        $album->assertSee(route('customer.media.favourites', ['focus_kind' => 'competition', 'focus_event' => $f['event']->uuid]))
            ->assertSee(route('customer.media.cart', ['focus_kind' => 'competition', 'focus_event' => $f['event']->uuid]));
        $this->get(route('customer.competitions.favourites', $f['event']))->assertRedirect(
            route('customer.media.favourites', ['focus_kind' => 'competition', 'focus_event' => $f['event']->uuid])
        );
    }

    public function test_ready_competition_photos_enter_an_idempotent_private_order_catalogue(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $catalogue = app(CompetitionOrderCatalogue::class);
        $first = $catalogue->readyRoutine($f['event'], $f['item']);
        $second = $catalogue->readyRoutine($f['event'], $f['item']);
        $this->assertSame($first['collection']->id, $second['collection']->id);
        $this->assertSame($first['assets']->first()->id, $second['assets']->first()->id);
        $this->assertSame($photo->storage_key, $first['assets']->first()->storage_key);
        $this->assertSame('customer', $first['collection']->visibility->value);
        $this->assertTrue(Storage::disk('local')->exists($first['assets']->first()->thumbnail_storage_key));
        $this->assertDatabaseCount('media_collections', 1);
        $this->assertDatabaseCount('media_assets', 1);
        $this->readyForPreview($f, false);
        try {
            $catalogue->readyRoutine($f['event'], $f['item']);
            $this->fail('An unready routine was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_improvisation_view_lists_only_ready_matching_routines_for_registered_customers(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $snapshot = $f['plan']->snapshot;
        $snapshot['nodes'][1]['title'] = 'Improvisation 12 & Under';
        $f['plan']->update(['snapshot' => $snapshot]);
        $customer = User::factory()->customer()->create();
        $list = route('customer.competitions.improvisation', $f['event']);
        $album = route('customer.competitions.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]);
        $this->actingAs($customer)->get($list)->assertForbidden();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id,
            'scheduling_event_id' => $f['event']->id, 'access_verified_at' => now()]);
        $this->get($list)->assertOk()->assertSee('Improvisation')->assertSee($album, false);
        $this->readyForPreview($f, false);
        $this->get($list)->assertOk()->assertSee('No ready improvisation routines yet')->assertDontSee($album, false);
    }

    public function test_recommended_routine_is_visible_before_event_login_but_full_gallery_stays_locked(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $customer = User::factory()->customer()->create();
        CustomerDancer::create(['user_id' => $customer->id, 'name' => 'Jessica Smith']);
        $this->actingAs($customer);
        $thumbnail = route('customer.media.recommendations.thumbnail', ['schedulingEvent' => $f['event'], 'item' => $f['item'], 'photo' => $photo->uuid]);
        $this->get(route('customer.media.index'))->assertOk()->assertSee('03 Jessica Smith')
            ->assertSee('Add event login to view')->assertSee($thumbnail, false);
        $this->get($thumbnail)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('customer.competitions.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]))->assertForbidden();
        $this->actingAs(User::factory()->customer()->create())->get($thumbnail)->assertNotFound();
        $this->readyForPreview($f, false);
        $this->actingAs($customer)->get($thumbnail)->assertNotFound();
        $this->get(route('customer.media.index'))->assertOk()->assertDontSee('03 Jessica Smith');
    }

    public function test_my_dancers_suggests_only_ready_owned_name_matches_without_restricting_whole_event(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $customer = User::factory()->customer()->create();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id, 'scheduling_event_id' => $f['event']->id,
            'access_verified_at' => now()]);
        CustomerDancer::create(['user_id' => $customer->id, 'name' => 'Jessie Smith',
            'programme_names' => ['Jessica Smith']]);
        $this->actingAs($customer);
        $url = route('customer.competitions.dancers', $f['event']);
        $album = route('customer.competitions.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]);
        $this->get($url)->assertOk()->assertSee('My Dancers')->assertSee($album, false)->assertSee('Matched: Jessie Smith')
            ->assertDontSee('/admin/scheduling-events/', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('customer.competitions.show', $f['event']))->assertOk()->assertSee($url, false)->assertSee('07 Jazz');

        $snapshot = $f['plan']->snapshot;
        foreach ($snapshot['nodes'] as &$node) {
            if ($node['uuid'] === $f['item']) {
                $node['title'] = 'Jessica Smithson';
            }
        }
        unset($node);
        $f['plan']->update(['snapshot' => $snapshot]);
        $this->get($url)->assertOk()->assertSee('No ready routines matched yet')->assertDontSee($album, false);
        $snapshot['nodes'][2]['title'] = 'Jessica Smith / Jazz';
        $f['plan']->update(['snapshot' => $snapshot]);
        $this->get($url)->assertOk()->assertSee($album, false);
        $this->readyForPreview($f, false);
        $this->get($url)->assertOk()->assertSee('No ready routines matched yet')->assertDontSee($album, false);
        $this->get(route('customer.competitions.show', $f['event']))->assertOk()->assertSee('Photos are being prepared');
        $this->actingAs(User::factory()->customer()->create())->get($url)->assertForbidden();
    }

    public function test_admin_studio_assignments_drive_ready_customer_suggestions_without_limiting_whole_event(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $studio = Studio::factory()->create(['name' => 'Test Dance Studio']);
        $otherStudio = Studio::factory()->create(['name' => 'Another Studio']);
        $save = route('admin.competition-gallery.routine-studio', $f['event']);
        $album = route('admin.competition-gallery.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]);
        $this->get($album)->assertOk()->assertSee('Routine studio')->assertSee($studio->name);
        $this->post($save, ['item_uuid' => $f['item'], 'studio_uuid' => $studio->uuid])->assertRedirect($album);
        $this->assertDatabaseHas('competition_routine_studios', ['scheduling_event_id' => $f['event']->id,
            'item_uuid' => $f['item'], 'studio_id' => $studio->id]);
        $this->assertDatabaseCount('competition_routine_studio_changes', 1);
        $this->post($save, ['item_uuid' => $f['item'], 'studio_uuid' => $studio->uuid, 'expected_studio_uuid' => $studio->uuid])->assertRedirect();
        $this->assertDatabaseCount('competition_routine_studio_changes', 1);
        $this->post($save, ['item_uuid' => $f['item'], 'studio_uuid' => $otherStudio->uuid])->assertConflict();
        $this->assertDatabaseHas('competition_routine_studios', ['item_uuid' => $f['item'], 'studio_id' => $studio->id]);
        $this->post($save, ['item_uuid' => (string) Str::uuid(), 'studio_uuid' => $otherStudio->uuid])->assertStatus(409);

        $customer = User::factory()->customer()->create();
        CustomerCompetitionRegistration::create(['user_id' => $customer->id, 'scheduling_event_id' => $f['event']->id, 'access_verified_at' => now()]);
        $customerStudio = CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $this->actingAs($customer);
        $list = route('customer.competitions.studios', $f['event']);
        $customerAlbum = route('customer.competitions.show', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]);
        $this->get($list)->assertOk()->assertSee('No ready studio routines yet')->assertDontSee($customerAlbum, false);
        $snapshot = $f['plan']->snapshot;
        $snapshot['nodes'][1]['title'] = 'Jazz Small Group 12 & Under';
        $f['plan']->update(['snapshot' => $snapshot]);
        $this->get($list)->assertOk()->assertSee('My Studios’ Routines')->assertSee($customerAlbum, false)
            ->assertSee($studio->name)->assertDontSee($otherStudio->name)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('customer.competitions.show', $f['event']))->assertOk()->assertSee($list, false);
        $customerStudio->delete();
        $this->get($list)->assertOk()->assertSee('Add a studio to your profile')->assertDontSee($customerAlbum, false);
        $this->get($customerAlbum)->assertOk();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $otherStudio->id, 'access_verified_at' => now()]);
        $this->get($list)->assertOk()->assertSee('No ready studio routines yet')->assertDontSee($customerAlbum, false);
        $this->readyForPreview($f, false);
        $this->get($list)->assertOk()->assertDontSee($customerAlbum, false);
        $this->actingAs(User::factory()->customer()->create())->get($list)->assertForbidden();
        $this->actingAs(User::factory()->crew()->create())->post($save, ['item_uuid' => $f['item'], 'studio_uuid' => $otherStudio->uuid])->assertForbidden();
    }

    public function test_studio_assignment_can_be_cleared_with_audited_change(): void
    {
        $f = $this->fixture();
        $studio = Studio::factory()->create();
        $save = route('admin.competition-gallery.routine-studio', $f['event']);
        $this->post($save, ['item_uuid' => $f['item'], 'studio_uuid' => $studio->uuid])->assertRedirect();
        $this->post($save, ['item_uuid' => $f['item'], 'expected_studio_uuid' => $studio->uuid])->assertRedirect();
        $this->assertDatabaseHas('competition_routine_studios', ['item_uuid' => $f['item'], 'studio_id' => null]);
        $this->assertDatabaseCount('competition_routine_studio_changes', 2);
    }

    public function test_customer_preview_requires_every_role_and_gates_direct_image_links(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f, false);
        $root = route('admin.competition-gallery.customer-preview', $f['event']);
        $album = route('admin.competition-gallery.customer-preview', ['schedulingEvent' => $f['event'], 'folder' => $f['item']]);
        $image = route('admin.competition-gallery.customer-preview.image', ['schedulingEvent' => $f['event'], 'photo' => $photo->uuid]);
        $this->get($root)->assertOk()->assertSee('Photos are being prepared')->assertDontSee('07 Jazz');
        $this->get($album)->assertNotFound();
        $this->get($image)->assertNotFound();
        $this->readyForPreview($f);
        $this->get($root)->assertOk()->assertSee('07 Jazz')->assertDontSee('Waiting for photographers')->assertDontSee('Photo uploads');
        $this->get($album)->assertOk()->assertSee($image, false)->assertDontSee('Use as cover')->assertDontSee('photos verified')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->photo($f, 'competition-photographer-p1', ['status' => 'reserved', 'verified_at' => null]);
        $this->get($album)->assertNotFound();
        $this->get($image)->assertNotFound();
        $this->assertSame('private', $f['gallery']->fresh()->visibility);
    }

    public function test_customer_preview_filters_days_and_covers_without_hiding_admin_media(): void
    {
        Storage::fake('local');
        $f = $this->fixture(true);
        $first = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $snapshot = $f['plan']->snapshot;
        $secondSection = (string) Str::uuid();
        $secondItem = (string) Str::uuid();
        $snapshot['nodes'][] = ['uuid' => $secondSection, 'kind' => 'section', 'title' => 'Unfinished section', 'number' => '8', 'parent_uuid' => $snapshot['nodes'][3]['uuid']];
        $snapshot['nodes'][] = ['uuid' => $secondItem, 'kind' => 'item', 'title' => 'Unfinished dancer', 'number' => '1', 'parent_uuid' => $secondSection];
        $f['plan']->update(['snapshot' => $snapshot]);
        $hidden = $this->photo(array_replace($f, ['item' => $secondItem]), 'competition-photographer-p1');
        $this->post(route('admin.competition-gallery.settings.update', $f['event']), ['action' => 'cover', 'version' => 0, 'photo_uuid' => $hidden->uuid])->assertRedirect();
        $root = route('admin.competition-gallery.customer-preview', $f['event']);
        $this->get($root)->assertOk()->assertSee('01 Friday')->assertDontSee('02 Saturday')->assertDontSee($hidden->uuid);
        $this->get(route('admin.competition-gallery.customer-preview', ['schedulingEvent' => $f['event'], 'folder' => $secondSection]))->assertNotFound();
        $this->get(route('admin.competition-gallery.image', ['schedulingEvent' => $f['event'], 'photo' => $hidden->uuid]))->assertOk();
        $this->post(route('admin.competition-gallery.settings.update', $f['event']), ['action' => 'cover', 'version' => 1, 'photo_uuid' => $first->uuid])->assertRedirect();
        $f['event']->update(['logo_path' => 'events/competition-logo.png']);
        $coverUrl = route('admin.competition-gallery.customer-preview.image', ['schedulingEvent' => $f['event'], 'photo' => $first->uuid, 'size' => 'thumbnail']);
        $this->get($root)->assertOk()->assertSee($f['event']->fresh()->crewLogoUrl(), false)->assertSee($coverUrl, false);
        $this->get(route('admin.competition-gallery.customer-preview', ['schedulingEvent' => $f['event'], 'folder' => $f['day']]))
            ->assertOk()->assertSee($coverUrl, false);
        $f['programme']->revisions()->create(['version' => 2, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        $this->get($root)->assertOk()->assertSee('Photos are being prepared')->assertDontSee($first->uuid);
        $this->get(route('admin.competition-gallery.customer-preview.image', ['schedulingEvent' => $f['event'], 'photo' => $first->uuid]))->assertNotFound();
    }

    public function test_customer_preview_remains_admin_only_and_event_scoped(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $file = $this->photo($f, 'competition-photographer-p1');
        $this->readyForPreview($f);
        $other = $this->fixture();
        $this->get(route('admin.competition-gallery.customer-preview.image', ['schedulingEvent' => $other['event'], 'photo' => $file->uuid]))->assertNotFound();
        $root = route('admin.competition-gallery.customer-preview', $f['event']);
        $image = route('admin.competition-gallery.customer-preview.image', ['schedulingEvent' => $f['event'], 'photo' => $file->uuid]);
        $this->actingAs(User::factory()->create(['type' => 'customer', 'is_admin' => false]));
        $this->get($root)->assertForbidden();
        $this->get($image)->assertForbidden();
        auth()->logout();
        $this->get($root)->assertRedirect();
        $this->get($image)->assertRedirect();
    }

    public function test_single_day_is_flat_and_additions_are_naturally_ordered(): void
    {
        $f = $this->fixture();
        $this->get($this->url($f))->assertOk()->assertSee('EDC926')->assertSee('07 Jazz 12 &amp; Under', false)->assertDontSee('01 Friday');
        CompetitionProgrammeAddition::create(['programme_upload_plan_id' => $f['plan']->id, 'node_uuid' => (string) Str::uuid(), 'node' => [
            'uuid' => (string) Str::uuid(), 'kind' => 'item', 'number' => '10', 'title' => 'Late competitor', 'parent_uuid' => $f['section'],
        ]]);
        $this->get($this->url($f, $f['section']))->assertOk()->assertSeeInOrder(['03 Jessica Smith', '10 Late competitor']);
        $this->get($this->url($f, $f['item']))->assertOk()->assertSee('No verified photos uploaded');
        $this->get($this->url($f, $f['day']))->assertNotFound();
    }

    public function test_multiple_days_and_breadcrumbs_match_capture_layout(): void
    {
        $f = $this->fixture(true);
        $this->get($this->url($f))->assertOk()->assertSeeInOrder(['01 Friday', '02 Saturday'])->assertDontSee('07 Jazz');
        $this->get($this->url($f, $f['day']))->assertOk()->assertSee('07 Jazz');
        $this->get($this->url($f, $f['item']))->assertOk()->assertSeeInOrder(['EDC926', '01 Friday', '07 Jazz', '03 Jessica Smith']);
    }

    public function test_verified_roles_share_album_with_no_pending_or_duplicate_receipts(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $first = $this->photo($f, 'competition-photographer-p1', ['file_name' => 'p1.jpg']);
        $second = $this->photo($f, 'competition-photographer-p2', ['file_name' => 'p2.jpg']);
        $this->photo($f, 'competition-photographer-p1', ['file_name' => 'copy.jpg', 'duplicate_of_file_id' => $first->id]);
        $pending = $this->photo($f, 'competition-photographer-p1', ['file_name' => 'pending.jpg', 'status' => 'reserved', 'verified_at' => null]);
        $this->get($this->url($f, $f['item']))->assertOk()->assertSee('2 uploaded photos')->assertSee('p1.jpg')->assertSee('p2.jpg')->assertDontSee('copy.jpg')->assertDontSee('pending.jpg')->assertDontSee($first->storage_key);
        $imageUrl = fn ($file) => route('admin.competition-gallery.image', ['schedulingEvent' => $f['event'], 'photo' => $file->uuid]);
        $response = $this->get($imageUrl($second))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($response->streamedContent())[2]);
        $this->assertNotSame(Storage::disk('local')->get($second->storage_key), $response->streamedContent());
        $this->getJson($imageUrl($second).'?size=original')->assertUnprocessable();
        $this->get($imageUrl($pending))->assertNotFound();
        Storage::disk('local')->delete($second->storage_key);
        $this->get($imageUrl($second))->assertNotFound();
    }

    public function test_large_albums_paginate_and_titles_are_escaped(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        for ($i = 1; $i <= 49; $i++) {
            $this->photo($f, 'competition-photographer-p1', ['file_name' => "photo-$i.jpg"]);
        }
        $first = $this->get($this->url($f, $f['item']))->assertOk()->assertSee('Page 1 of 2')->assertSee('photo-48.jpg')->assertDontSee('photo-49.jpg');
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->get($this->url($f, $f['item']).'&page=2')->assertOk()->assertSee('photo-49.jpg')->assertDontSee('photo-48.jpg');
        $snapshot = $f['plan']->snapshot;
        $snapshot['nodes'][2]['title'] = '<script>alert(1)</script>';
        $f['plan']->update(['snapshot' => $snapshot]);
        $this->get($this->url($f, $f['section']))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_access_is_admin_only_and_scoped_to_event(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $photo = $this->photo($f, 'competition-photographer-p1');
        $other = $this->fixture();
        $this->get($this->url($other, $f['item']))->assertNotFound();
        $imageUrl = route('admin.competition-gallery.image', ['schedulingEvent' => $other['event'], 'photo' => $photo->uuid]);
        $this->get($imageUrl)->assertNotFound();
        $ownImage = route('admin.competition-gallery.image', ['schedulingEvent' => $f['event'], 'photo' => $photo->uuid]);
        $this->actingAs(User::factory()->create(['type' => 'crew', 'is_admin' => false]));
        $this->get($this->url($f))->assertForbidden();
        $this->get($ownImage)->assertForbidden();
        auth()->logout();
        $this->get($this->url($f))->assertRedirect();
        $this->get($ownImage)->assertRedirect();
    }

    public function test_confirmed_programme_can_be_browsed_before_upload_and_old_gallery_is_flagged(): void
    {
        $f = $this->fixture();
        $f['programme']->revisions()->create(['version' => 2, 'confirmed_at' => now(), 'snapshot' => $f['revision']->snapshot]);
        $this->get($this->url($f))->assertOk()->assertSee('earlier programme');
        $f['gallery']->delete();
        $this->get($this->url($f))->assertOk()->assertSee('07 Jazz')->assertDontSee('earlier programme');
        $f['event']->update(['event_type' => 'concert']);
        $this->get($this->url($f))->assertNotFound();
    }
}
