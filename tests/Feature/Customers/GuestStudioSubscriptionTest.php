<?php

namespace Tests\Feature\Customers;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Actions\SendGuestGalleryReleaseNotifications;
use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Features\Customers\Models\GuestStudioSubscription;
use App\Features\Customers\Notifications\GuestGalleryReleased;
use App\Features\Customers\Services\CustomerPrivacyData;
use App\Features\Media\Models\MediaCollection;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GuestStudioSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-28 10:00:00');
        Notification::fake();
    }

    public function test_concert_browse_groups_events_under_one_studio_and_competitions_omit_subtitles(): void
    {
        $studio = Studio::factory()->create(['name' => 'One Studio']);
        $this->concert($studio, 'First showcase');
        $this->concert($studio, 'Second showcase');
        $page = $this->get(route('guest-galleries.index', ['type' => 'concert']))->assertOk()
            ->assertSee('One Studio')->assertDontSee('First showcase')->assertDontSee('Second showcase');
        $this->assertSame(1, substr_count($page->getContent(), 'href="'.route('guest-galleries.studios.show', $studio).'"'));
        $this->get(route('guest-galleries.studios.show', $studio))->assertOk()
            ->assertSee('First showcase')->assertSee('Second showcase')->assertSee('Register now')
            ->assertSee(route('guest-galleries.studios.subscribe', $studio), false);
        SchedulingEvent::create(['name' => 'Competition title', 'event_type' => 'competition', 'event_date' => today()]);
        $this->get(route('guest-galleries.index'))->assertOk()->assertSee('Competition title')->assertDontSee('public-gallery-subtitle');
    }

    public function test_one_subscription_notifies_for_each_future_release_without_historical_or_duplicate_emails(): void
    {
        $studio = Studio::factory()->create();
        $past = $this->concert($studio, 'Previously released');
        $this->release($past, now()->subDay());
        $first = $this->concert($studio, 'First new event');
        $this->subscribe($studio);
        $subscription = GuestStudioSubscription::firstOrFail();
        $this->assertNotSame('guest@example.test', $subscription->getRawOriginal('email'));
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->travel(1)->minutes();
        $this->release($first, now());
        $this->assertSame(1, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->travel(30)->days();
        $second = $this->concert($studio, 'Created later');
        $this->release($second, now());
        $this->assertSame(1, app(SendGuestGalleryReleaseNotifications::class)->execute($second['event']));
        $this->assertDatabaseCount('guest_gallery_registrations', 2);
        Notification::assertCount(2);
        $this->assertDatabaseMissing('guest_gallery_registrations', ['scheduling_event_id' => $past['event']->id]);
    }

    public function test_repeat_registration_preserves_start_and_existing_event_opt_in_is_not_sent_twice(): void
    {
        $studio = Studio::factory()->create();
        $event = $this->concert($studio, 'Showcase');
        $this->subscribe($studio);
        $consent = GuestStudioSubscription::firstOrFail()->consent_at;
        $this->travel(1)->minutes();
        $this->subscribe($studio);
        $this->assertDatabaseCount('guest_studio_subscriptions', 1);
        $this->assertTrue(GuestStudioSubscription::firstOrFail()->consent_at->equalTo($consent));
        $this->post(route('guest-galleries.notifications.store', $event['event']), ['email' => 'guest@example.test', 'notification_consent' => 1])->assertRedirect();
        $this->release($event, now());
        $this->assertSame(1, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->assertDatabaseCount('guest_gallery_registrations', 1);
    }

    public function test_signed_unsubscribe_stops_future_and_queued_notifications_and_allows_explicit_resubscription(): void
    {
        $studio = Studio::factory()->create();
        $first = $this->concert($studio, 'First');
        $this->subscribe($studio);
        $this->travel(1)->minutes();
        $this->release($first, now());
        app(SendGuestGalleryReleaseNotifications::class)->execute();
        $registration = GuestGalleryRegistration::firstOrFail();
        $subscription = GuestStudioSubscription::firstOrFail();
        $notification = new GuestGalleryReleased($registration);
        $mail = $notification->toMail((object) []);
        $this->assertStringContainsString('/galleries/studio-notifications/', implode(' ', $mail->outroLines));
        $this->get(route('guest-galleries.studios.unsubscribe', $subscription))->assertForbidden();
        $this->get(URL::temporarySignedRoute('guest-galleries.studios.unsubscribe', now()->addHour(), ['subscription' => $subscription]))->assertOk();
        $this->assertFalse($notification->shouldSend((object) [], 'mail'));
        $second = $this->concert($studio, 'Second');
        $this->release($second, now());
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->travel(1)->minutes();
        $this->subscribe($studio);
        $this->assertFalse($notification->shouldSend((object) [], 'mail'));
        $third = $this->concert($studio, 'Third');
        $this->travel(1)->minutes();
        $this->release($third, now());
        $this->assertSame(1, app(SendGuestGalleryReleaseNotifications::class)->execute());
        Notification::assertCount(2);
    }

    public function test_subscription_requires_consent_and_does_not_grant_gallery_access(): void
    {
        $studio = Studio::factory()->create();
        $fixture = $this->concert($studio, 'Showcase');
        $this->post(route('guest-galleries.studios.subscribe', $studio), ['email' => 'guest@example.test'])->assertSessionHasErrors('notification_consent');
        $this->subscribe($studio);
        $this->get(route('guest-galleries.concerts.show', $fixture['concert']))->assertForbidden();
        $hidden = Studio::factory()->create();
        $this->get(route('guest-galleries.studios.show', $hidden))->assertNotFound();
        $this->post(route('guest-galleries.studios.subscribe', $hidden), ['email' => 'guest@example.test', 'notification_consent' => 1])->assertSessionHasErrors('email');
    }

    public function test_signed_in_subscriber_receives_inbox_notification_and_privacy_redaction_stops_subscription(): void
    {
        $studio = Studio::factory()->create();
        $fixture = $this->concert($studio, 'Showcase');
        $user = User::factory()->customer()->create(['email' => 'customer@example.test']);
        $this->actingAs($user)->post(route('guest-galleries.studios.subscribe', $studio), ['notification_consent' => 1])->assertRedirect();
        $this->travel(1)->minutes();
        $this->release($fixture, now());
        app(SendGuestGalleryReleaseNotifications::class)->execute();
        Notification::assertSentTo($user, GuestGalleryReleased::class, fn ($notification, $channels) => $channels === ['mail', 'database']);
        $privacy = app(CustomerPrivacyData::class);
        $this->assertCount(1, $privacy->export($user)['studio_gallery_subscriptions']);
        $privacy->redact($user, 'anonymous@example.test');
        $subscription = GuestStudioSubscription::firstOrFail();
        $this->assertNotNull($subscription->unsubscribed_at);
        $this->assertSame('anonymous@example.test', $subscription->email);
    }

    private function subscribe(Studio $studio): void
    {
        $this->post(route('guest-galleries.studios.subscribe', $studio), ['email' => ' Guest@Example.test ', 'notification_consent' => 1])->assertRedirect()->assertSessionHas('status');
    }

    private function release(array $fixture, $at): void
    {
        $fixture['collection']->update(['status' => 'published', 'visibility' => 'customer', 'published_at' => $at]);
    }

    private function concert(Studio $studio, string $name): array
    {
        $event = SchedulingEvent::create(['studio_id' => $studio->id, 'name' => $name, 'event_type' => 'concert', 'event_date' => today()->addDays(2)]);
        $booking = ConcertBooking::create(['studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000', 'status' => 'pending']);
        $item = ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'scheduling_event_id' => $event->id, 'item_type' => 'concert', 'title' => $name, 'venue_name' => 'Theatre', 'venue_address' => 'Example street', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'event_date' => $event->event_date]);
        $concert = Concert::factory()->published()->create(['studio_id' => $studio->id, 'concert_booking_item_id' => $item->id, 'name' => $name]);
        $collection = MediaCollection::factory()->create(['concert_id' => $concert->id, 'competition_id' => null, 'media_type' => 'photo', 'status' => 'draft', 'visibility' => 'private', 'published_at' => null]);

        return compact('event', 'concert', 'collection');
    }
}
