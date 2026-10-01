<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Actions\SendGuestGalleryReleaseNotifications;
use App\Features\Customers\Models\CustomerInboxNotification;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Features\Customers\Models\GuestStudioSubscription;
use App\Features\Customers\Notifications\GuestGalleryReleased;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Customers\Services\StudioMessageReads;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\ConcertPhotoReleases;
use App\Features\Publicity\Services\PublicityEventAccess;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Mail\ConcertTrackerMessage;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Services\ConcertPhotoUploadStatus;
use App\Features\Tracking\Services\PhotoReleaseEmails;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PhotoReleaseAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_messages_cover_two_events_once_and_keep_customer_and_studio_inboxes_separate(): void
    {
        [$admin, $studio, $group, $concerts] = $this->release();
        $owner = $this->owner($studio);
        $secondOwner = $this->owner($studio);
        $customer = User::factory()->customer()->create();
        foreach ([$owner, $customer] as $user) {
            CustomerStudio::create(['user_id' => $user->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
            $this->guest($concerts[0], $user->email); // Must not cause duplicate emails.
        }
        $this->guest($concerts[0], 'guest@example.test');
        $this->guest($concerts[1], 'guest@example.test');
        $this->subscription($studio, 'subscriber@example.test');
        $this->guest($concerts[0], 'opted-out@example.test');
        $this->subscription($studio, 'opted-out@example.test', ['unsubscribed_at' => now()]);
        $inactive = User::factory()->customer()->create(['is_active' => false]);
        $this->guest($concerts[0], $inactive->email);
        $unverified = User::factory()->customer()->unverified()->create();
        CustomerStudio::create(['user_id' => $unverified->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $emails = app(PhotoReleaseEmails::class);
        $page = $emails->page($group);
        $this->assertCount(6, $page['audience']);
        $this->assertSame(2, $page['audience']->where('audience', 'studio')->count());
        $this->assertSame(2, $page['audience']->where('audience', 'customer')->count());
        $this->assertSame(2, $page['audience']->where('audience', 'guest')->count());
        $this->assertStringContainsString('Junior Concert', $page['guestMessage']['body']);
        $this->assertStringContainsString('Senior Concert', $page['studioMessage']['body']);
        $this->assertStringContainsString(app(PublicityEventAccess::class)->studioLink($studio), $page['studioMessage']['body']);
        $this->actingAs($admin)->get(route('admin.concert-photo-releases.email', $group))->assertOk()
            ->assertSee('Customer account announcement')->assertSee('Guest gallery announcement')->assertSee('Studio announcement')->assertSee('Send all release announcements');
        $this->post(route('admin.concert-photo-releases.email.send', $group), ['confirm_send' => true,
            'email_subject' => 'Account photos', 'email_body' => 'CUSTOMER MESSAGE',
            'guest_email_subject' => 'Guest photos', 'guest_email_body' => 'GUEST MESSAGE',
            'studio_email_subject' => 'Studio photos', 'studio_email_body' => $page['studioMessage']['body'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $emails->send($group, $admin));
        $deliveries = ConcertTrackerEmailDelivery::where('photo_release_group_id', $group->id)->get();
        $this->assertCount(6, $deliveries);
        foreach ($deliveries as $delivery) {
            (new SendConcertTrackerEmail($delivery->id))->handle();
            (new SendConcertTrackerEmail($delivery->id))->handle();
            $this->assertSame('sent', $delivery->fresh()->status);
        }
        Mail::assertSentCount(6);
        Mail::assertSent(ConcertTrackerMessage::class, fn ($mail) => $mail->hasTo('guest@example.test') && $mail->messageSubject === 'Guest photos'
            && str_contains($mail->messageBody, 'GUEST MESSAGE') && str_contains($mail->messageBody, 'Unsubscribe') && $mail->accountUrl === route('guest-galleries.studios.show', $studio));
        Mail::assertSent(ConcertTrackerMessage::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->accountUrl === route('customer.studio-communications.index', ['studio' => $studio, 'tab' => 'messages']));
        $this->assertDatabaseHas('customer_inbox_notifications', ['user_id' => $customer->id, 'subject' => 'Account photos']);
        $this->assertDatabaseHas('customer_inbox_notifications', ['user_id' => $owner->id, 'subject' => 'Account photos']);
        $this->assertSame(2, $deliveries->where('recipient_email', $owner->email)->count());
        $this->assertSame(1, $deliveries->where('recipient_email', $owner->email)->where('photo_release_mail_kind', 'gallery')->count());
        $this->assertSame(1, $deliveries->where('recipient_email', $owner->email)->where('photo_release_mail_kind', 'studio')->count());
        $this->assertSame(0, CustomerInboxNotification::whereIn('user_id', [$owner->id, $secondOwner->id])->where('subject', 'Studio photos')->count());
        $this->assertSame(1, StudioConversationMessage::where('photo_release_group_id', $group->id)->count());
        $history = app(StudioCommunicationHistory::class)->forUser($owner, $studio);
        $this->assertSame(1, $history['communications']->where('subject', 'Studio photos')->count());
        $this->assertSame(0, $history['communications']->where('subject', 'Account photos')->count());
        $this->assertSame(1, app(StudioMessageReads::class)->unreadCount($studio));
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());
        $this->assertSame(0, $emails->send($group, $admin));
    }

    public function test_unsubscribing_and_revoking_access_cancel_queued_mail_and_grouped_release_suppresses_legacy_jobs(): void
    {
        [$admin, $studio, $group, $concerts] = $this->release();
        $owner = $this->owner($studio);
        $this->guest($concerts[0], 'guest@example.test');
        $emails = app(PhotoReleaseEmails::class);
        $this->assertSame(2, $emails->send($group, $admin));
        $subscription = GuestStudioSubscription::where('email_hash', hash('sha256', 'guest@example.test'))->sole();
        $this->get(URL::temporarySignedRoute('guest-galleries.studios.unsubscribe', now()->addHour(), ['subscription' => $subscription]))->assertOk();
        StudioAccountAccess::where('user_id', $owner->id)->update(['status' => 'revoked', 'revoked_at' => now()]);
        foreach (ConcertTrackerEmailDelivery::where('photo_release_group_id', $group->id)->get() as $delivery) {
            (new SendConcertTrackerEmail($delivery->id))->handle();
            $this->assertSame('cancelled', $delivery->fresh()->status);
        }
        Mail::assertNothingSent();
        $old = GuestGalleryRegistration::create(['scheduling_event_id' => $concerts[0]->bookingItem->scheduling_event_id,
            'email' => 'old@example.test', 'email_hash' => hash('sha256', 'old@example.test'), 'consent_at' => now()->subDay()]);
        $this->assertFalse((new GuestGalleryReleased($old))->shouldSend((object) [], 'mail'));
        $this->assertSame(0, app(SendGuestGalleryReleaseNotifications::class)->execute());
        Notification::assertNothingSent();
    }

    public function test_release_page_validates_all_three_editors_and_retries_only_failed_recipients(): void
    {
        [$admin, $studio, $group, $concerts] = $this->release();
        $this->owner($studio);
        $this->guest($concerts[0], 'guest@example.test');
        $this->actingAs($admin)->post(route('admin.concert-photo-releases.email.send', $group), ['confirm_send' => true, 'guest_email_subject' => "Header\nInjection", 'studio_email_body' => ''])
            ->assertSessionHasErrors(['guest_email_subject', 'studio_email_body']);
        $this->assertDatabaseCount('concert_tracker_email_deliveries', 0);
        $emails = app(PhotoReleaseEmails::class);
        $this->assertSame(2, $emails->send($group, $admin));
        $failed = ConcertTrackerEmailDelivery::where('recipient_email', 'guest@example.test')->sole();
        $failed->update(['status' => 'failed']);
        $this->assertSame(1, $emails->send($group, $admin, ['guest_email_subject' => 'Retry guest']));
        $this->assertSame('Retry guest', $failed->fresh()->subject);
        $this->assertDatabaseCount('concert_tracker_email_deliveries', 2);
        $this->actingAs(User::factory()->customer()->create())->post(route('admin.concert-photo-releases.email.send', $group), ['confirm_send' => true])->assertForbidden();
    }

    public function test_changed_studio_code_cancels_the_stale_family_link_and_retry_uses_the_new_link(): void
    {
        [$admin, $studio, $group] = $this->release();
        $this->owner($studio);
        $emails = app(PhotoReleaseEmails::class);
        $emails->send($group, $admin);
        $delivery = ConcertTrackerEmailDelivery::where('photo_release_group_id', $group->id)->sole();
        $oldBody = $delivery->body;
        $studio->update(['code' => 'NEWCODE']);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Mail::assertNothingSent();
        $this->assertSame(1, $emails->send($group->fresh(), $admin));
        $this->assertNotSame($oldBody, $delivery->fresh()->body);
        $this->assertStringContainsString(app(PublicityEventAccess::class)->studioLink($studio), $delivery->fresh()->body);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_customer_and_studio_emails_use_each_accounts_first_name_without_changing_the_shared_preview(): void
    {
        [$admin, $studio, $group, $concerts] = $this->release();
        $owner = $this->owner($studio);
        $owner->update(['first_name' => 'Jesstudio', 'last_name' => 'Ownerberg']);
        $otherOwner = $this->owner($studio);
        $otherOwner->update(['first_name' => 'Casey', 'last_name' => 'North']);
        $customer = User::factory()->customer()->create(['first_name' => 'Gustomer', 'last_name' => 'Accountford']);
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $this->guest($concerts[0], 'guest@example.test');
        $emails = app(PhotoReleaseEmails::class);
        $page = $emails->page($group);
        $this->assertStringStartsWith('Hi {{ first_name }},', $page['renderedTemplate']['body']);
        $this->assertStringStartsWith('Hi {{ first_name }},', $page['studioMessage']['body']);
        $this->assertStringContainsString('Your account already has access', $page['renderedTemplate']['body']);
        $this->assertStringNotContainsString('If you have not added the studio yet', $page['renderedTemplate']['body']);
        $this->assertSame(4, $emails->send($group, $admin));
        foreach ([$owner, $otherOwner, $customer] as $user) {
            $delivery = ConcertTrackerEmailDelivery::where('photo_release_group_id', $group->id)->where('recipient_email', $user->email)->sole();
            $this->assertStringStartsWith('Hi '.$user->first_name.',', $delivery->body);
            $this->assertStringNotContainsString('Hi '.$user->name, $delivery->body);
            $this->assertStringNotContainsString('{{ first_name }}', $delivery->body);
        }
        $guest = ConcertTrackerEmailDelivery::where('recipient_email', 'guest@example.test')->sole();
        $this->assertStringStartsWith('Hi everyone,', $guest->body);
        $this->assertStringStartsWith('Hi {{ first_name }},', $emails->page($group)['renderedTemplate']['body']);
    }

    private function release(): array
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();
        $this->mock(ConcertPhotoUploadStatus::class)->shouldReceive('forEvent')->andReturn(['key' => 'complete', 'label' => 'Complete', 'detail' => '', 'sourceUrl' => null, 'completedAt' => now(), 'photoRoles' => []]);
        $admin = User::factory()->admin()->create();
        $studio = Studio::factory()->create(['code' => 'TESTSTUDIO', 'status' => 'active']);
        $booking = ConcertBooking::create(['status' => 'approved', 'studio_name' => $studio->name, 'contact_name' => 'Studio Owner', 'contact_email' => 'owner@example.test', 'contact_phone' => '0400000000', 'wants_concert_photography' => true]);
        $albums = [];
        $concerts = [];
        foreach (['Junior Concert', 'Senior Concert'] as $name) {
            $event = SchedulingEvent::create(['studio_id' => $studio->id, 'name' => $name, 'event_type' => 'concert', 'event_date' => today()]);
            $item = ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'scheduling_event_id' => $event->id, 'item_type' => 'concert', 'title' => $name, 'event_date' => today(), 'approval_status' => 'approved', 'venue_name' => 'Theatre', 'venue_address' => 'Test Street', 'starts_at' => '18:00', 'finishes_at' => '20:00']);
            $concert = Concert::factory()->create(['studio_id' => $studio->id, 'concert_booking_item_id' => $item->id, 'name' => $name, 'event_date' => today()]);
            $album = MediaCollection::create(['concert_id' => $concert->id, 'name' => $name, 'media_type' => 'photo', 'catalogue_mode' => 'managed', 'status' => 'draft', 'visibility' => 'private', 'storage_disk' => 'local', 'storage_prefix' => 'photos/']);
            MediaAsset::create(['media_collection_id' => $album->id, 'media_type' => 'photo', 'storage_disk' => 'local', 'storage_key' => $album->uuid.'/photo.jpg', 'status' => 'available', 'is_visible' => true]);
            $albums[] = $album->uuid;
            $concerts[] = $concert;
        }
        $releases = app(ConcertPhotoReleases::class);
        $group = $releases->create($admin, ['name' => 'Studio concerts', 'collection_uuids' => $albums]);
        $releases->release($group, $admin);

        return [$admin, $studio, $group->fresh(), $concerts];
    }

    private function owner(Studio $studio): User
    {
        $user = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'email' => $user->email, 'role' => 'owner', 'status' => 'active']);

        return $user;
    }

    private function guest(Concert $concert, string $email): void
    {
        GuestGalleryAccess::create(['concert_id' => $concert->id, 'gallery_type' => 'concert', 'email' => $email, 'email_hash' => hash('sha256', mb_strtolower($email)), 'expires_at' => now()->addDays(30), 'last_used_at' => now()]);
    }

    private function subscription(Studio $studio, string $email, array $attributes = []): void
    {
        GuestStudioSubscription::create($attributes + ['studio_id' => $studio->id, 'email' => $email, 'email_hash' => hash('sha256', $email), 'consent_at' => now()->subDay()]);
    }
}
