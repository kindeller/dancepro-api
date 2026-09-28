<?php

namespace Tests\Feature\Media;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Publicity\Services\PublicityEventAccess;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GallerySharingTest extends TestCase
{
    use RefreshDatabase;

    private function studio(): Studio
    {
        $studio = Studio::factory()->create(['name' => 'Shareable studio', 'status' => 'active']);
        $studio->forceFill(['gallery_code_hash' => bcrypt('EXAMPLE')])->save();

        return $studio;
    }

    public function test_admin_can_copy_links_and_download_qr_without_a_slideshow(): void
    {
        $studio = $this->studio();
        $event = SchedulingEvent::create(['name' => 'Shareable competition', 'event_type' => 'competition', 'event_date' => now()]);
        CompetitionGallerySetting::create(['scheduling_event_id' => $event->id, 'login_code_hash' => bcrypt('EXAMPLE')]);
        $access = app(PublicityEventAccess::class);
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.publicity.sharing.index'))->assertOk()->assertSee($event->name)->assertSee($access->link($event));
        $this->get(route('admin.publicity.sharing.index', ['type' => 'studio']))->assertOk()->assertSee($studio->name)->assertSee($access->studioLink($studio));
        $this->get(route('admin.publicity.sharing.index', ['q' => 'absent']))->assertOk()->assertDontSee($event->name);
        foreach ([route('admin.publicity.sharing.event-qr', $event), route('admin.publicity.sharing.studio-qr', $studio)] as $url) {
            $png = $this->get($url)->assertOk()->assertHeader('content-type', 'image/png')->getContent();
            $this->assertSame([1000, 1000], array_slice(getimagesizefromstring($png), 0, 2));
        }
        $this->assertDatabaseCount('tv_slideshows', 0);
        $this->actingAs(User::factory()->create(['type' => 'customer', 'is_admin' => false]));
        $this->get(route('admin.publicity.sharing.index'))->assertForbidden();
        $this->get(route('admin.publicity.sharing.studio-qr', $studio))->assertForbidden();
        $this->get(route('admin.publicity.sharing.event-qr', $event))->assertForbidden();
    }

    public function test_studio_link_works_without_events_is_idempotent_and_invalidates_when_code_changes(): void
    {
        $studio = $this->studio();
        $access = app(PublicityEventAccess::class);
        $link = $access->studioLink($studio);
        $customer = User::factory()->create(['type' => 'customer', 'is_admin' => false, 'email_verified_at' => now()]);
        $this->actingAs($customer);
        for ($i = 0; $i < 2; $i++) {
            $this->get($link)->assertRedirect(route('publicity.join.complete'));
            $this->get(route('publicity.join.complete'))->assertRedirect(route('customer.media.index'));
        }
        $this->assertNotNull(CustomerStudio::where('user_id', $customer->id)->sole()->access_verified_at);
        $this->assertDatabaseCount('customer_studios', 1);
        $this->assertDatabaseCount('customer_account_activities', 1);
        $this->assertDatabaseCount('concert_access_grants', 0);
        $this->assertDatabaseCount('order_media_entitlements', 0);
        $this->get($link.'changed')->assertForbidden();
        $studio->forceFill(['gallery_code_hash' => bcrypt('REPLACEMENT')])->save();
        $this->get($link)->assertForbidden();
        $fresh = $access->studioLink($studio);
        $studio->update(['status' => 'inactive']);
        $this->get($fresh)->assertForbidden();
    }

    public function test_new_customer_can_sign_up_verify_and_receive_the_scanned_studio(): void
    {
        Notification::fake();
        $studio = $this->studio();
        $this->get(app(PublicityEventAccess::class)->studioLink($studio))->assertRedirect(route('customer.login'));
        $this->get(route('customer.login'))->assertOk()->assertSee($studio->name)->assertSee('Create account');
        $this->get(route('customer.register'))->assertOk()->assertSee($studio->name);
        $this->post(route('customer.register.store'), ['name' => 'QR Customer', 'email' => 'qr-new@example.test',
            'password' => 'Example-password-123', 'password_confirmation' => 'Example-password-123', 'policy_accepted' => '1'])
            ->assertRedirect(route('customer.verification.notice'));
        $this->assertDatabaseCount('customer_studios', 0);
        $customer = User::where('email', 'qr-new@example.test')->sole();
        $verification = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $customer->id, 'hash' => sha1($customer->email)]);
        $this->get($verification)->assertRedirect(route('publicity.join.complete'));
        $this->get(route('publicity.join.complete'))->assertRedirect(route('customer.media.index'))->assertSessionMissing('publicity_join');
        $this->assertDatabaseHas('customer_studios', ['user_id' => $customer->id, 'studio_id' => $studio->id]);
    }

    public function test_existing_customer_login_resumes_competition_access_without_tv_enabled(): void
    {
        $event = SchedulingEvent::create(['name' => 'Competition link', 'event_type' => 'competition', 'event_date' => now()]);
        CompetitionGallerySetting::create(['scheduling_event_id' => $event->id, 'login_code_hash' => bcrypt('EXAMPLE')]);
        $customer = User::factory()->create(['type' => 'customer', 'is_admin' => false, 'email_verified_at' => now(), 'password' => bcrypt('Example-password-123')]);
        $link = app(PublicityEventAccess::class)->link($event);
        $this->get($link)->assertRedirect(route('customer.login'));
        $this->post(route('customer.login.store'), ['email' => $customer->email, 'password' => 'Example-password-123'])->assertRedirect(route('publicity.join.complete'));
        $this->get(route('publicity.join.complete'))->assertRedirect(route('customer.media.index'));
        $this->assertDatabaseHas('customer_competition_registrations', ['user_id' => $customer->id, 'scheduling_event_id' => $event->id]);
        $event->update(['cancelled_at' => now()]);
        $this->get($link)->assertForbidden();
    }
}
