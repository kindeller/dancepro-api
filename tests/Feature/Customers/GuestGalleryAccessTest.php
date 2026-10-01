<?php

namespace Tests\Feature\Customers;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Actions\SendGuestGalleryReleaseNotifications;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Notifications\GuestGalleryReleased;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestGalleryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-23 10:00:00');
        Storage::fake('local');
    }

    public function test_public_switcher_lists_events_from_seven_days_before_start_and_records_one_registration(): void
    {
        $visible = SchedulingEvent::create(['name' => 'Seven day event', 'event_type' => 'competition', 'event_date' => today()->addDays(7)]);
        SchedulingEvent::create(['name' => 'Eight day event', 'event_type' => 'competition', 'event_date' => today()->addDays(8)]);
        SchedulingEvent::create(['name' => 'Old unreleased event', 'event_type' => 'competition', 'event_date' => today()->subDay()]);

        $this->get(route('guest-galleries.index'))->assertOk()->assertSee('Competitions')->assertSee('Concerts')
            ->assertSee('Seven day event')->assertSee('Coming soon')->assertSee('Register now')
            ->assertDontSee('Eight day event')->assertDontSee('Old unreleased event');
        $input = ['email' => ' Guest@Example.test ', 'notification_consent' => '1'];
        $this->post(route('guest-galleries.notifications.store', $visible), $input)->assertRedirect()
            ->assertSessionHas('status');
        $this->post(route('guest-galleries.notifications.store', $visible), $input)->assertRedirect();
        $this->assertDatabaseCount('guest_gallery_registrations', 1);
        $registration = DB::table('guest_gallery_registrations')->first();
        $this->assertSame(hash('sha256', 'guest@example.test'), $registration->email_hash);
        $this->assertNotSame('guest@example.test', $registration->email);
    }

    public function test_release_notification_is_sent_once_and_signed_link_prefills_email_without_unlocking_gallery(): void
    {
        Notification::fake();
        $fixture = $this->concert(false);
        $this->post(route('guest-galleries.notifications.store', $fixture['event']), [
            'email' => 'notify@example.test', 'notification_consent' => '1',
        ])->assertRedirect();
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());

        $fixture['collection']->update(['status' => 'published', 'visibility' => 'customer', 'published_at' => now()]);
        $this->assertSame(1, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());
        Notification::assertSentOnDemand(GuestGalleryReleased::class, function (GuestGalleryReleased $notification, array $channels, object $notifiable): bool {
            $url = $notification->toMail($notifiable)->actionUrl;
            $this->get($url)->assertRedirect(route('guest-galleries.concerts.access', $this->concertRecord));

            return $notifiable->routes['mail'] === 'notify@example.test';
        });
        $this->get(route('guest-galleries.concerts.show', $fixture['concert']))->assertForbidden();
    }

    public function test_signed_in_customer_registers_with_one_button_and_receives_account_notification(): void
    {
        Notification::fake();
        $fixture = $this->concert(false);
        $event = $fixture['event'];
        $customer = User::factory()->customer()->create(['email' => 'signed-in@example.test']);
        $this->actingAs($customer)->get(route('guest-galleries.index', ['type' => 'concert']))
            ->assertRedirect(route('customer.media.index'));
        $this->post(route('guest-galleries.notifications.store', $event), [])->assertRedirect()
            ->assertSessionHas('status', 'We’ll notify you here and by email when this gallery is ready.');
        $this->assertDatabaseHas('guest_gallery_registrations', [
            'scheduling_event_id' => $event->id, 'user_id' => $customer->id,
            'email_hash' => hash('sha256', 'signed-in@example.test'),
        ]);

        $fixture['collection']->update(['status' => 'published', 'visibility' => 'customer', 'published_at' => now()]);
        $this->assertSame(1, app(SendGuestGalleryReleaseNotifications::class)->execute());
        Notification::assertSentTo($customer, GuestGalleryReleased::class, fn ($notification, $channels): bool => $channels === ['mail', 'database']);
    }

    private ?Concert $concertRecord = null;

    public function test_guest_can_unlock_and_browse_released_concert_photos_with_email_and_studio_code(): void
    {
        $fixture = $this->concert(true);
        $show = route('guest-galleries.concerts.show', $fixture['concert']);
        $this->get($show)->assertForbidden();
        $this->get(route('guest-galleries.index', ['type' => 'concert']))->assertOk()->assertSee($fixture['studio']->name);
        $this->get(route('guest-galleries.studios.show', $fixture['studio']))->assertRedirect(route('guest-galleries.studios.access', $fixture['studio']));
        $this->post(route('guest-galleries.concerts.unlock', $fixture['concert']), [
            'email' => 'guest@example.test', 'login_code' => 'WRONG-CODE',
        ])->assertSessionHasErrors('login_code')->assertSessionMissing('_old_input.login_code');
        $this->post(route('guest-galleries.concerts.unlock', $fixture['concert']), [
            'email' => 'guest@example.test', 'login_code' => 'studio-gallery',
        ])->assertRedirect($show);
        $album = route('guest-galleries.concerts.show', ['concert' => $fixture['concert'], 'folder' => $fixture['collection']->uuid]);
        $image = route('guest-galleries.concerts.image', ['concert' => $fixture['concert'], 'asset' => $fixture['asset']->uuid]);
        $this->get($show)->assertOk()->assertSee('Album One')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get($album)->assertOk()->assertSee($image, false);
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $selection = route('guest-galleries.concerts.selections', $fixture['concert']);
        $this->post($selection, ['asset_uuid' => $fixture['asset']->uuid, 'action' => 'favourite'])->assertRedirect();
        $this->post($selection, ['asset_uuid' => $fixture['asset']->uuid, 'action' => 'add'])->assertRedirect();
        $this->assertDatabaseHas('guest_gallery_selections', ['media_asset_id' => $fixture['asset']->id,
            'is_favourite' => true, 'in_cart' => true]);
        $this->get($album)->assertOk()->assertSee('Favourites')->assertSee('Cart')->assertSee('Remove from favourites')->assertSee('Remove from cart');
        $this->get(route('guest-galleries.concerts.cart', $fixture['concert']))->assertOk()
            ->assertSee('Your cart')->assertSee('Create an account to continue to checkout');
        $this->get(route('guest-galleries.concerts.cart', ['concert' => $fixture['concert'], 'tab' => 'favourites']))
            ->assertOk()->assertSee('Saved favourites')->assertSee('Album One')->assertSee('Remove from favourites');
        $this->get(route('guest-galleries.concerts.checkout', ['concert' => $fixture['concert'], 'account' => 'login']))
            ->assertRedirect(route('login'));
        $this->assertSame(route('customer.concerts.photos.cart', $fixture['studio']), session('url.intended'));

        $accessUuid = DB::table('guest_gallery_accesses')->value('uuid');
        DB::table('guest_gallery_accesses')->update(['expires_at' => now()->subMinute()]);
        $this->flushSession();
        $this->get($album)->assertForbidden();
        $this->post(route('guest-galleries.concerts.unlock', $fixture['concert']), [
            'email' => ' GUEST@example.test ', 'login_code' => 'STUDIO-GALLERY',
        ])->assertRedirect();
        $this->assertDatabaseCount('guest_gallery_accesses', 1);
        $this->assertSame($accessUuid, DB::table('guest_gallery_accesses')->value('uuid'));
        $this->get($album)->assertOk()->assertSee('Favourites')->assertSee('Cart')->assertSee('Remove from favourites')->assertSee('Remove from cart');

        Notification::fake();
        $this->post(route('customer.register.store'), [
            'name' => 'Guest Buyer', 'email' => 'guest@example.test', 'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password', 'policy_accepted' => '1',
        ])->assertRedirect(route('customer.verification.notice'));
        $user = User::where('email', 'guest@example.test')->firstOrFail();
        $this->assertTrue(CustomerStudio::where('user_id', $user->id)->where('studio_id', $fixture['studio']->id)
            ->whereNotNull('access_verified_at')->exists());
        $this->assertTrue(CustomerConcertPhotoSelection::where('user_id', $user->id)
            ->where('media_asset_id', $fixture['asset']->id)->where('is_favourite', true)->where('in_cart', true)->exists());
        $this->assertDatabaseCount('guest_gallery_accesses', 0);
        $this->assertDatabaseCount('guest_gallery_selections', 0);
    }

    public function test_one_studio_code_unlocks_released_events_but_not_other_studios_or_unreleased_media(): void
    {
        $first = $this->concert(true);
        $second = $this->concert(true, 'SECOND');
        $second['concert']->update(['studio_id' => $first['studio']->id]);
        $second['concert']->bookingItem->update(['item_type' => 'dress_rehearsal']);
        $private = $this->concert(false, 'PRIVATE');
        $private['concert']->update(['studio_id' => $first['studio']->id]);
        $other = $this->concert(true, 'OTHER');
        $access = route('guest-galleries.studios.access', $first['studio']);
        $this->get($access)->assertOk()->assertSee('Studio access code')->assertSee('Open studio galleries');
        $this->post(route('guest-galleries.studios.unlock', $first['studio']), [
            'email' => 'guest@example.test', 'login_code' => 'wrong',
        ])->assertSessionHasErrors('login_code');
        $this->post(route('guest-galleries.studios.unlock', $first['studio']), [
            'email' => 'guest@example.test', 'login_code' => 'STUDIO-GALLERY',
        ])->assertRedirect(route('guest-galleries.studios.show', $first['studio']));
        $expires = DB::table('guest_gallery_accesses')->value('expires_at');
        $this->travel(1)->days();
        foreach ([$first, $second] as $fixture) {
            $show = route('guest-galleries.concerts.show', $fixture['concert']);
            $this->get(route('guest-galleries.concerts.access', $fixture['concert']))->assertRedirect($show);
            $this->get($show)->assertOk()->assertSee(route('guest-galleries.studios.show', $first['studio']), false);
            $this->get(route('guest-galleries.concerts.image', [$fixture['concert'], $fixture['asset']->uuid]))->assertOk();
            $this->post(route('guest-galleries.concerts.selections', $fixture['concert']), [
                'asset_uuid' => $fixture['asset']->uuid, 'action' => 'add',
            ])->assertRedirect();
        }
        $this->get(route('guest-galleries.concerts.show', $second['concert']))->assertOk()
            ->assertSee('class="header-back" href="'.route('guest-galleries.studios.show', $first['studio']).'"', false);
        $this->get(route('guest-galleries.studios.show', $first['studio']))->assertOk()
            ->assertSee(route('guest-galleries.concerts.show', $second['concert']), false);
        $this->get(route('guest-galleries.concerts.cart', $first['concert']))->assertOk()
            ->assertViewHas('cart', fn ($cart) => $cart->count() === 2);
        $this->assertSame([$expires], DB::table('guest_gallery_accesses')->distinct()->pluck('expires_at')->all());
        $this->get(route('guest-galleries.concerts.show', $other['concert']))->assertForbidden();
        $this->get(route('guest-galleries.concerts.show', $private['concert']))->assertNotFound();
        $this->get(route('guest-galleries.concerts.image', [$private['concert'], $private['asset']->uuid]))->assertNotFound();
        $this->get(route('guest-galleries.concerts.image', [$first['concert'], $other['asset']->uuid]))->assertNotFound();
        $private['collection']->update(['status' => 'published', 'visibility' => 'customer', 'published_at' => now()]);
        $this->get(route('guest-galleries.concerts.show', $private['concert']))->assertOk();
        $this->assertSame([$expires], DB::table('guest_gallery_accesses')->distinct()->pluck('expires_at')->all());
        $this->travel(30)->days();
        $this->get(route('guest-galleries.studios.show', $first['studio']))->assertRedirect($access);
        $this->get(route('guest-galleries.concerts.show', $second['concert']))->assertForbidden();
    }

    public function test_guest_can_unlock_and_browse_only_ready_competition_media(): void
    {
        $fixture = $this->competition();
        $show = route('guest-galleries.competitions.show', $fixture['event']);
        $album = route('guest-galleries.competitions.show', ['schedulingEvent' => $fixture['event'], 'folder' => $fixture['item']]);
        $image = route('guest-galleries.competitions.image', ['schedulingEvent' => $fixture['event'], 'photo' => $fixture['photo']->uuid]);
        $this->get($show)->assertForbidden();
        $this->get(route('guest-galleries.index'))->assertOk()->assertSee('Released competition');
        $this->post(route('guest-galleries.competitions.unlock', $fixture['event']), [
            'email' => 'guest@example.test', 'login_code' => 'event-gallery',
        ])->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('07 Jazz 12 & Under');
        $this->get($album)->assertOk()->assertSee($image, false)
            ->assertSee('Individual photos')->assertSee('All photos from this routine')
            ->assertSee('Photos + video package')->assertSee('Individual video from this routine');
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $selection = route('guest-galleries.competitions.selections', $fixture['event']);
        $this->post($selection, ['product' => 'routine_photos', 'item_uuid' => $fixture['item'], 'action' => 'favourite'])
            ->assertRedirect();
        $this->post($selection, ['product' => 'photo', 'item_uuid' => $fixture['item'],
            'photo_uuid' => $fixture['photo']->uuid, 'action' => 'add'])->assertRedirect();
        $this->post(route('guest-galleries.competitions.selections.photos', $fixture['event']), [
            'item_uuid' => $fixture['item'], 'photo_uuids' => [$fixture['photo']->uuid], 'action' => 'favourite',
        ])->assertRedirect();
        $this->assertDatabaseHas('guest_gallery_selections', ['photo_uuid' => $fixture['photo']->uuid,
            'is_favourite' => true, 'in_cart' => true]);
        $this->get(route('guest-galleries.competitions.favourites', $fixture['event']))->assertOk()
            ->assertSee('Album photos')->assertSee('Jessica Smith');
        $this->get(route('guest-galleries.competitions.cart', $fixture['event']))->assertOk()
            ->assertSee('Individual photo')->assertSee('Create an account to continue to checkout');
        $this->get(route('guest-galleries.competitions.checkout', $fixture['event']))
            ->assertRedirect(route('customer.register'));
        $this->assertSame(route('customer.competitions.cart', $fixture['event']), session('url.intended'));
        $this->get(route('guest-galleries.competitions.dancers', $fixture['event']))->assertOk()
            ->assertSee('Create your profile to see your dancers’ routines');
        $this->get(route('guest-galleries.competitions.studios', $fixture['event']))->assertOk()
            ->assertSee('Create your profile to see your studio’s routines');
        $this->get(route('guest-galleries.competitions.improvisation', $fixture['event']))->assertOk()
            ->assertSee('Improvisation');
        ProgrammePhotoContribution::where('crew_role_id', CrewRole::where('code', 'competition-photographer-p2')->value('id'))
            ->update(['status' => 'receiving', 'completed_at' => null]);
        $this->get($album)->assertNotFound();
        $this->get($image)->assertNotFound();
    }

    public function test_existing_customer_sign_in_claims_matching_guest_activity(): void
    {
        $fixture = $this->concert(true);
        $user = User::factory()->customer()->create([
            'email' => 'existing@example.test', 'password' => 'long-secure-password',
        ]);
        $this->post(route('guest-galleries.concerts.unlock', $fixture['concert']), [
            'email' => 'existing@example.test', 'login_code' => 'STUDIO-GALLERY',
        ])->assertRedirect();
        $this->post(route('guest-galleries.concerts.selections', $fixture['concert']), [
            'asset_uuid' => $fixture['asset']->uuid, 'action' => 'favourite',
        ])->assertRedirect();

        $this->post(route('customer.login.store'), [
            'email' => 'existing@example.test', 'password' => 'long-secure-password',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(CustomerConcertPhotoSelection::where('user_id', $user->id)
            ->where('media_asset_id', $fixture['asset']->id)->where('is_favourite', true)->exists());
        $this->assertDatabaseCount('guest_gallery_accesses', 0);
    }

    private function concert(bool $released, string $code = 'STUDIO-GALLERY'): array
    {
        $studio = Studio::factory()->create();
        $studio->forceFill(['code' => $code, 'gallery_code_hash' => Hash::make($code)])->save();
        $event = SchedulingEvent::create([
            'studio_id' => $studio->id, 'name' => 'Guest concert', 'event_type' => 'concert', 'event_date' => today()->addDays(3),
        ]);
        $booking = ConcertBooking::create([
            'studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'director@example.test',
            'contact_phone' => '0400000000', 'status' => 'pending',
        ]);
        $item = ConcertBookingItem::create([
            'concert_booking_id' => $booking->id, 'item_type' => 'concert', 'title' => 'Guest concert',
            'venue_name' => 'Theatre', 'venue_address' => 'Example street', 'starts_at' => '18:00', 'finishes_at' => '20:00',
            'event_date' => $event->event_date, 'scheduling_event_id' => $event->id,
        ]);
        $concert = Concert::factory()->published()->create([
            'studio_id' => $studio->id, 'concert_booking_item_id' => $item->id, 'name' => 'Guest concert',
        ]);
        $this->concertRecord = $concert;
        $collection = MediaCollection::factory()->create([
            'concert_id' => $concert->id, 'competition_id' => null, 'name' => 'Album One', 'media_type' => 'photo',
            'status' => $released ? 'published' : 'draft', 'visibility' => $released ? 'customer' : 'private',
            'published_at' => $released ? now() : null,
        ]);
        $bytes = $this->jpeg();
        $key = 'guest-concert/'.$concert->uuid.'/photo.jpg';
        Storage::disk('local')->put($key, $bytes);
        $asset = MediaAsset::factory()->create([
            'media_collection_id' => $collection->id, 'media_type' => 'photo', 'status' => 'available', 'is_visible' => true,
            'storage_disk' => 'local', 'storage_key' => $key, 'mime_type' => 'image/jpeg',
        ]);

        return compact('studio', 'event', 'concert', 'collection', 'asset');
    }

    private function competition(): array
    {
        $event = SchedulingEvent::create(['name' => 'Released competition', 'event_type' => 'competition', 'event_date' => today()]);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $day = (string) Str::uuid();
        $section = (string) Str::uuid();
        $item = (string) Str::uuid();
        $snapshot = ['event_type' => 'competition', 'event_code' => 'EDC926', 'nodes' => [
            ['uuid' => $day, 'kind' => 'day', 'date' => today()->toDateString(), 'title' => 'Wednesday'],
            ['uuid' => $section, 'kind' => 'section', 'number' => '7', 'title' => 'Jazz 12 & Under', 'parent_uuid' => $day],
            ['uuid' => $item, 'kind' => 'item', 'number' => '3', 'title' => 'Jessica Smith', 'parent_uuid' => $section],
        ]];
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        $plan = ProgrammeUploadPlan::create([
            'programme_id' => $programme->id, 'programme_revision_id' => $revision->id,
            'digest' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot,
        ]);
        $gallery = ProgrammePhotoGallery::create(['scheduling_event_id' => $event->id, 'programme_upload_plan_id' => $plan->id]);
        $event->shifts()->create(['shift_date' => today(), 'period' => 'morning']);
        $contribution = null;
        foreach (['competition-photographer-p1', 'competition-photographer-p2'] as $code) {
            $role = CrewRole::where('code', $code)->firstOrFail();
            $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
            $current = ProgrammePhotoContribution::create([
                'programme_upload_plan_id' => $plan->id, 'item_uuid' => $item, 'crew_role_id' => $role->id,
                'assignment_uuids' => [], 'status' => 'complete', 'completed_at' => now(),
            ]);
            $contribution ??= $current;
        }
        $bytes = $this->jpeg();
        Storage::disk('local')->put('guest-competition/photo.jpg', $bytes);
        $photo = ProgrammePhotoFile::create([
            'programme_photo_contribution_id' => $contribution->id, 'programme_photo_gallery_id' => $gallery->id,
            'client_photo_uuid' => (string) Str::uuid(), 'file_name' => 'photo.jpg', 'byte_count' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'status' => 'verified', 'verified_at' => now(),
            'storage_disk' => 'local', 'storage_key' => 'guest-competition/photo.jpg',
        ]);
        CompetitionGallerySetting::create([
            'scheduling_event_id' => $event->id, 'version' => 1, 'login_code_hash' => Hash::make('EVENT-GALLERY'),
        ]);

        return compact('event', 'programme', 'plan', 'gallery', 'section', 'item', 'photo');
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, 70, 100, 140));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        unset($image);

        return $bytes;
    }
}
