<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CustomerPurchasedMedia;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Services\ActivateOrderMediaEntitlement;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerMediaTest extends TestCase
{
    use RefreshDatabase;

    private function add(string $code = 'studio-test-code')
    {
        if (auth()->id()) {
            foreach (Studio::all() as $studio) {
                CustomerStudio::firstOrCreate(['user_id' => auth()->id(), 'studio_id' => $studio->id], ['access_verified_at' => now()]);
            }
        }

        return $this->post(route('customer.media.concerts.store'), ['concert_code' => $code, 'year' => 2026]);
    }

    public function test_account_wording_and_media_home_cover_competitions_and_concerts(): void
    {
        $this->get(route('customer.register'))->assertOk()->assertSee('concert photos and concert videos');
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Saved competition', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        CustomerCompetitionRegistration::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id, 'access_verified_at' => now()]);
        $this->actingAs($user)->get(route('customer.media.index'))->assertOk()->assertSee('My Media')->assertSee('Add media')->assertSee('Saved competition')->assertSee(route('customer.profile.edit'), false);
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.media.index'))->assertOk()->assertDontSee('Saved competition');
    }

    public function test_media_home_separates_recommendations_purchases_and_browsing_access(): void
    {
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $concert = Concert::factory()->create(['name' => 'Purchased concert']);
        $collection = MediaCollection::factory()->create(['concert_id' => $concert->id, 'media_type' => MediaType::Photo]);
        $paid = Order::factory()->paid()->create(['user_id' => $user->id]);
        OrderItem::factory()->create(['order_id' => $paid->id, 'media_collection_id' => $collection->id, 'media_asset_id' => null]);
        $draft = Order::factory()->create(['user_id' => $other->id]);
        OrderItem::factory()->create(['order_id' => $draft->id, 'media_collection_id' => $collection->id, 'media_asset_id' => null]);

        $this->actingAs($user)->get(route('customer.media.index'))->assertOk()
            ->assertSee('Recommended Media')->assertSee('My Dancers')->assertSee('My Studio')
            ->assertSee('Photos')->assertSee('Videos')->assertSee('My Events')->assertSee('Purchased concert');
        $this->actingAs($other)->get(route('customer.media.index'))->assertOk()
            ->assertSee('No purchased photos are linked to your account yet.');
    }

    public function test_competition_purchases_from_multiple_orders_share_one_flat_event_album(): void
    {
        Storage::fake('local');
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Regional competition', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        CustomerCompetitionRegistration::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'access_verified_at' => now()]);
        $firstCollection = MediaCollection::factory()->forCompetition($event->id)->create(['name' => 'Routine one']);
        $secondCollection = MediaCollection::factory()->forCompetition($event->id)->create(['name' => 'Routine two']);
        $first = MediaAsset::factory()->create(['media_collection_id' => $firstCollection->id, 'display_name' => 'First photo',
            'thumbnail_storage_disk' => 'local', 'thumbnail_storage_key' => 'thumbs/first.jpg']);
        $second = MediaAsset::factory()->create(['media_collection_id' => $secondCollection->id, 'display_name' => 'Second photo',
            'thumbnail_storage_disk' => 'local', 'thumbnail_storage_key' => 'thumbs/second.jpg']);
        Storage::disk('local')->put('thumbs/first.jpg', 'first thumbnail');
        Storage::disk('local')->put('thumbs/second.jpg', 'second thumbnail');
        foreach ([$first, $second, $first] as $asset) {
            $order = Order::factory()->paid()->create(['user_id' => $user->id]);
            OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $asset->media_collection_id,
                'media_asset_id' => $asset->id]);
        }

        $album = app(CustomerPurchasedMedia::class)->albumForUser($user, 'competition', $event->uuid);
        $this->assertSame(2, $album['assets']->count());
        $this->assertCount(1, app(CustomerPurchasedMedia::class)->forUser($user)['photos']);
        $this->actingAs($user)->get(route('customer.media.index'))->assertOk()->assertSee('View media →')
            ->assertSee(route('customer.media.library.thumbnail', ['kind' => 'competition', 'event' => $event->uuid, 'asset' => $first->uuid]), false);
        $this->get(route('customer.media.library.show', ['kind' => 'competition', 'event' => $event->uuid]))->assertOk()
            ->assertSee('First photo')->assertSee('Second photo')->assertDontSee('Routine one')->assertDontSee('Routine two');
        $this->get(route('customer.media.library.thumbnail', ['kind' => 'competition', 'event' => $event->uuid, 'asset' => $first->uuid]))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($other)->get(route('customer.media.library.show', ['kind' => 'competition', 'event' => $event->uuid]))->assertNotFound();
    }

    public function test_new_order_entitlement_freezes_the_exact_purchased_photo_set(): void
    {
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Snapshot competition', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        CustomerCompetitionRegistration::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id, 'access_verified_at' => now()]);
        $collection = MediaCollection::factory()->forCompetition($event->id)->create(['media_type' => MediaType::Photo]);
        $first = MediaAsset::factory()->create(['media_collection_id' => $collection->id]);
        MediaAsset::factory()->create(['media_collection_id' => $collection->id]);
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $collection->id, 'media_asset_id' => null]);
        app(ActivateOrderMediaEntitlement::class)->execute($item, 'photo', collect([$first]));

        $album = app(CustomerPurchasedMedia::class)->albumForUser($user, 'competition', $event->uuid);
        $this->assertSame([$first->uuid], $album['assets']->pluck('uuid')->all());
        MediaAsset::factory()->create(['media_collection_id' => $collection->id]);
        $album = app(CustomerPurchasedMedia::class)->albumForUser($user, 'competition', $event->uuid);
        $this->assertSame([$first->uuid], $album['assets']->pluck('uuid')->all());
    }

    public function test_order_history_is_private_and_lists_only_the_customer_orders(): void
    {
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $mine = Order::factory()->paid()->create(['user_id' => $user->id, 'total_amount' => 4500]);
        $foreign = Order::factory()->paid()->create(['user_id' => $other->id]);
        $this->actingAs($user)->get(route('customer.media.orders.index'))->assertOk()
            ->assertSee($mine->uuid)->assertSee('$45.00')->assertDontSee($foreign->uuid)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_my_events_combines_verified_studio_photos_and_video_access_once(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        $otherStudio = Studio::factory()->create();
        CustomerStudio::create(['user_id' => $user->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $concert = Concert::factory()->published()->passwordProtected('studio-test-code')->create([
            'name' => 'Studio event for media', 'studio_id' => $studio->id, 'event_date' => '2026-06-01',
            'external_gallery_url' => 'https://photos.example.test/studio-event',
        ]);
        Concert::factory()->published()->create(['name' => 'Other studio event', 'studio_id' => $otherStudio->id,
            'event_date' => '2026-06-01', 'external_gallery_url' => 'https://photos.example.test/other']);

        $this->actingAs($user)->get(route('customer.media.index'))->assertOk()
            ->assertSee('Studio event for media')->assertSee('View event →')
            ->assertSee($concert->galleryForDisplay(), false)->assertDontSee('Other studio event');
        $this->post(route('customer.media.concerts.store'), ['concert_code' => 'studio-test-code', 'year' => 2026])->assertRedirect();
        $response = $this->get(route('customer.media.index'))->assertOk()->assertSee('View event →')
            ->assertSee(route('concerts.show', $concert), false)->assertDontSee('Other studio event');
        $this->assertSame(1, substr_count($response->getContent(), 'Studio event for media'));
    }

    public function test_recommendation_thumbnail_requires_a_matching_ready_routine(): void
    {
        $event = SchedulingEvent::create(['name' => 'Other competition', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        $url = route('customer.media.recommendations.thumbnail', ['schedulingEvent' => $event,
            'item' => '00000000-0000-4000-8000-000000000001', 'photo' => '00000000-0000-4000-8000-000000000002']);

        $this->get($url)->assertRedirect(route('customer.login'));
        $this->actingAs(User::factory()->customer()->create())->get($url)->assertNotFound();
    }

    public function test_one_code_adds_multiple_concerts_persistently_without_extending_dates(): void
    {
        $user = User::factory()->customer()->create();
        $first = Concert::factory()->state(['event_date' => '2026-06-01'])->published()->passwordProtected('studio-test-code')->create(['available_until' => now()->addMonth()]);
        $second = Concert::factory()->state(['event_date' => '2026-06-01'])->published()->passwordProtected('studio-test-code')->create(['studio_id' => $first->studio_id]);
        $other = Concert::factory()->state(['event_date' => '2026-06-01'])->published()->passwordProtected('another-code')->create();
        $this->actingAs($user);
        $this->add()->assertRedirect(route('customer.media.index'));
        $this->assertDatabaseCount('concert_access_grants', 2);
        $grant = ConcertAccessGrant::where('concert_id', $first->id)->firstOrFail();
        $expires = $grant->expires_at;
        $claimed = $grant->claimed_at;
        $first->update(['available_until' => now()->addMonths(2)]);
        $this->add()->assertRedirect();
        $this->assertDatabaseCount('concert_access_grants', 2);
        $this->assertTrue($expires->equalTo($grant->fresh()->expires_at));
        $this->assertTrue($claimed->equalTo($grant->fresh()->claimed_at));
        $this->assertStringNotContainsString('studio-test-code', json_encode($grant->metadata));
        $this->get(route('customer.media.index'))->assertOk()->assertSee($first->name)->assertSee($second->name)->assertDontSee($other->name);
        $this->flushSession();
        $this->actingAs($user)->get(route('concerts.show', $first))->assertOk()->assertSee('Media is not available yet')->assertDontSee('Unlock concert');
        $this->get(route('concerts.show', $other))->assertOk()->assertSee('Unlock concert');
        $this->actingAs(User::factory()->customer()->create())->get(route('concerts.show', $first))->assertOk()->assertSee('Unlock concert');
    }

    public function test_invalid_unapproved_disabled_expired_and_wrong_codes_cannot_grant_access(): void
    {
        Concert::factory()->state(['event_date' => '2026-06-01'])->awaitingApproval()->passwordProtected('studio-test-code')->create();
        Concert::factory()->state(['event_date' => '2026-06-01'])->published()->disabled()->passwordProtected('studio-test-code')->create();
        Concert::factory()->state(['event_date' => '2026-06-01'])->expired()->passwordProtected('studio-test-code')->create();
        Concert::factory()->state(['event_date' => '2026-06-01'])->published()->passwordProtected('studio-test-code')->create(['available_from' => now()->addDay()]);
        Concert::factory()->state(['event_date' => '2026-06-01'])->published()->passwordProtected('different-code')->create();
        $this->actingAs(User::factory()->customer()->create());
        $this->add()->assertSessionHasErrors('concert_code')->assertSessionMissing('_old_input.concert_code');
        $this->assertDatabaseCount('concert_access_grants', 0);
    }

    public function test_revocation_expiry_and_code_changes_gate_saved_access_and_old_sessions(): void
    {
        $concert = Concert::factory()->state(['event_date' => '2026-06-01'])->published()->passwordProtected('studio-test-code')->create();
        $this->actingAs(User::factory()->customer()->create());
        $this->add()->assertRedirect();
        $grant = ConcertAccessGrant::firstOrFail();
        $concert->update(['access_password_hash' => 'changed-code']);
        $this->withSession(["concert_access.{$concert->uuid}" => true])->get(route('concerts.show', $concert))->assertSee('Unlock concert');
        $this->add('changed-code')->assertRedirect();
        $this->get(route('concerts.show', $concert))->assertDontSee('Unlock concert');
        $grant->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->add('changed-code')->assertSessionHasErrors('concert_code');
        $this->get(route('concerts.show', $concert))->assertSee('Unlock concert');
        $grant->update(['status' => 'active', 'revoked_at' => null, 'expires_at' => now()->subDay()]);
        $this->add('changed-code')->assertSessionHasErrors('concert_code');
        $this->get(route('concerts.show', $concert))->assertSee('Unlock concert');
        $this->assertDatabaseCount('concert_access_grants', 1);
    }

    public function test_customer_access_routes_require_active_customer_account(): void
    {
        $this->get(route('customer.media.index'))->assertRedirect(route('customer.login'));
        $this->add()->assertRedirect(route('customer.login'));
        $this->actingAs(User::factory()->crew()->create())->get(route('customer.media.index'))->assertForbidden();
        $this->add()->assertForbidden();
        $this->actingAs(User::factory()->customer()->inactive()->create())->get(route('customer.media.index'))->assertForbidden();
        $this->add()->assertForbidden();
    }
}
