<?php

namespace Tests\Feature\Customers;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerCompetitionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = [], string $code = 'SPRING-TEST'): SchedulingEvent
    {
        $contact = CompetitionContact::firstOrCreate(['code' => 'EDC'], ['name' => 'Express', 'code' => 'EDC', 'organiser_name' => 'Test organiser', 'organiser_email' => 'organiser@example.test', 'organiser_phone' => '0400000000']);
        $event = SchedulingEvent::create(array_replace(['name' => 'September competition', 'event_type' => 'competition',
            'event_date' => '2026-09-04', 'competition_contact_id' => $contact->id], $attributes));
        CompetitionGallerySetting::create(['scheduling_event_id' => $event->id, 'version' => 1, 'login_code_hash' => Hash::make($code)]);

        return $event;
    }

    private function register(SchedulingEvent $event, string $code = 'SPRING-TEST')
    {
        CustomerDancer::firstOrCreate(['user_id' => auth()->id(), 'name' => 'Test dancer']);

        return $this->post(route('customer.competitions.store'), ['event_uuid' => $event->uuid, 'login_code' => $code]);
    }

    public function test_customer_selects_event_and_verifies_code_once_with_stable_access(): void
    {
        $user = User::factory()->customer()->create();
        $event = $this->event();
        $this->actingAs($user)->get(route('customer.competitions.show', $event))->assertForbidden();
        $this->get(route('customer.competitions.index'))->assertOk()->assertSee('Select an event')->assertSee('4 Sep 2026')->assertDontSee('SPRING-TEST');
        foreach ([' spring-test ', 'SPRING-TEST'] as $code) {
            $this->register($event, $code)->assertRedirect(route('customer.competitions.show', $event));
        }
        $this->assertDatabaseCount('customer_competition_registrations', 1);
        $this->assertNotNull(CustomerCompetitionRegistration::first()->access_verified_at);
        $this->get(route('customer.competitions.show', $event))->assertOk()->assertSee('Photos are being prepared')->assertDontSee('Customer preview')
            ->assertSee(route('customer.media.favourites', ['focus_kind' => 'competition', 'focus_event' => $event->uuid]))
            ->assertSee(route('customer.media.cart', ['focus_kind' => 'competition', 'focus_event' => $event->uuid]));
        $this->get(route('customer.competitions.favourites', $event))->assertRedirect(
            route('customer.media.favourites', ['focus_kind' => 'competition', 'focus_event' => $event->uuid])
        );
        $event->update(['event_date' => '2026-10-01']);
        CompetitionGallerySetting::where('scheduling_event_id', $event->id)->update(['login_code_hash' => Hash::make('CHANGED-TEST')]);
        $this->get(route('customer.competitions.show', $event))->assertOk();
        $this->assertSame('customer.media.index', $user->homeRouteName());
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.competitions.show', $event))->assertForbidden();
    }

    public function test_same_media_code_can_identify_multiple_events_with_independent_login_codes(): void
    {
        $first = $this->event();
        $second = $this->event(['name' => 'Regional event', 'event_date' => '2026-09-10', 'competition_contact_id' => $first->competition_contact_id], 'REGION-TEST');
        $this->assertSame($first->eventCode(), $second->eventCode());
        $this->actingAs(User::factory()->customer()->create());
        $this->get(route('customer.competitions.index'))->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->register($first, $first->eventCode())->assertSessionHasErrors('login_code');
        $this->register($second)->assertSessionHasErrors('login_code')->assertSessionMissing('_old_input.login_code');
        $this->assertDatabaseCount('customer_competition_registrations', 0);
        $this->register($first)->assertRedirect(route('customer.competitions.show', $first));
        $this->get(route('customer.competitions.show', $second))->assertForbidden();
        $this->register($second, 'REGION-TEST')->assertRedirect(route('customer.competitions.show', $second));
        $this->assertDatabaseCount('customer_competition_registrations', 2);
    }

    public function test_unconfigured_cancelled_and_invalid_events_cannot_register(): void
    {
        $event = $this->event();
        $this->actingAs(User::factory()->customer()->create());
        $this->post(route('customer.competitions.store'), ['event_code' => 'EDC926'])->assertSessionHasErrors(['event_uuid', 'login_code']);
        $event->update(['cancelled_at' => now()]);
        $this->register($event)->assertSessionHasErrors('login_code');
        $this->get(route('customer.competitions.index'))->assertOk()->assertDontSee($event->name);
        $event->update(['cancelled_at' => null]);
        CompetitionGallerySetting::where('scheduling_event_id', $event->id)->delete();
        $this->register($event)->assertSessionHasErrors('login_code');
        $this->get(route('customer.competitions.index'))->assertOk()->assertSee($event->name);
        $this->get(route('customer.competitions.show', $event))->assertForbidden();
        $this->assertDatabaseCount('customer_competition_registrations', 0);
    }

    public function test_legacy_registration_requires_login_verification_and_inactive_access_is_denied(): void
    {
        $user = User::factory()->customer()->create();
        $event = $this->event();
        CustomerCompetitionRegistration::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id]);
        $this->actingAs($user)->get(route('customer.competitions.show', $event))->assertForbidden();
        $this->register($event)->assertRedirect();
        $this->assertDatabaseCount('customer_competition_registrations', 1);
        $this->get(route('customer.competitions.show', $event))->assertOk();
        $event->update(['cancelled_at' => now()]);
        $this->get(route('customer.competitions.show', $event))->assertForbidden();
        $event->update(['cancelled_at' => null, 'event_type' => 'concert']);
        $this->get(route('customer.competitions.show', $event))->assertForbidden();
        $user->update(['is_active' => false]);
        $this->get(route('customer.competitions.index'))->assertForbidden();
        $this->register($event)->assertForbidden();
    }

    public function test_admin_can_set_code_without_exposing_it_or_overwriting_other_settings(): void
    {
        $event = $this->event();
        $this->actingAs(User::factory()->staff()->create());
        $url = route('admin.competition-gallery.settings.update', $event);
        $input = ['action' => 'login_code', 'version' => 1, 'login_code' => 'NEW-TEST'];
        $this->post($url, $input)->assertRedirect();
        $settings = CompetitionGallerySetting::where('scheduling_event_id', $event->id)->firstOrFail();
        $this->assertTrue(Hash::check('NEW-TEST', $settings->login_code_hash));
        $this->assertArrayNotHasKey('login_code_hash', $settings->toArray());
        $history = DB::table('competition_gallery_setting_changes')->where('scheduling_event_id', $event->id)->first();
        $this->assertStringNotContainsString('NEW-TEST', $history->after);
        $this->assertStringNotContainsString($settings->login_code_hash, $history->after);
        $this->get(route('admin.competition-gallery.settings', $event))->assertOk()->assertSee('A login code is set')->assertDontSee('NEW-TEST')->assertDontSee($settings->login_code_hash);
        $this->post($url, $input)->assertConflict();
        $this->post($url, ['action' => 'title', 'version' => 2, 'display_title' => 'Gallery title'])->assertRedirect();
        $this->assertSame($settings->login_code_hash, $settings->fresh()->login_code_hash);
        $this->post($url, ['action' => 'login_code', 'version' => 3, 'login_code' => 'bad!'])->assertSessionHasErrors('login_code')->assertSessionMissing('_old_input.login_code');
        $this->get(route('customer.competitions.show', $event))->assertForbidden();
        $this->register($event, 'NEW-TEST')->assertRedirect();
        $this->actingAs(User::factory()->customer()->create())->post($url, $input)->assertForbidden();
        $this->actingAs(User::factory()->crew()->create())->get(route('customer.competitions.index'))->assertOk();
        $this->get(route('customer.competitions.show', $event))->assertForbidden();
        $this->register($event)->assertSessionHasErrors('login_code');
        $this->register($event, 'NEW-TEST')->assertRedirect();
        $this->get(route('customer.competitions.show', $event))->assertOk();
    }
}
