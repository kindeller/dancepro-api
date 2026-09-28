<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_tabs_open_as_separate_focused_pages(): void
    {
        $user = User::factory()->customer()->create();
        $this->actingAs($user)->get(route('customer.profile.edit'))
            ->assertOk()->assertSee('My details')->assertSee('Account email')->assertDontSee('Add dancer')
            ->assertDontSee('Media galleries')->assertDontSee('Studios &amp; concerts', false)->assertDontSee('Staff');
        $this->get(route('customer.profile.edit', ['tab' => 'dancers']))
            ->assertOk()->assertSee('Add dancer')->assertDontSee('Account email');
        $this->get(route('customer.profile.edit', ['tab' => 'studios']))
            ->assertOk()->assertSee('Add or verify studio')->assertDontSee('Save preferences');
        $this->get(route('customer.profile.edit', ['tab' => 'preferences']))
            ->assertOk()->assertSee('Save preferences')->assertDontSee('Add or verify studio');
        $this->get(route('customer.profile.edit', ['tab' => 'wrong']))->assertSessionHasErrors('tab');
    }

    public function test_details_update_only_owned_profile_and_safe_account_fields(): void
    {
        $user = User::factory()->customer()->create();
        $originalEmail = $user->email;
        $other = User::factory()->customer()->create();
        $this->actingAs($user)->post(route('customer.profile.store'), ['action' => 'details', 'name' => 'Customer Name',
            'preferred_name' => 'Customer', 'phone' => '+61 400 123 456', 'user_id' => $other->id, 'email' => 'attacker@example.test', 'is_admin' => true])->assertRedirect();
        $this->assertSame('Customer Name', $user->fresh()->name);
        $this->assertSame($originalEmail, $user->fresh()->email);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame('Customer', $user->fresh()->customerProfile->preferred_name);
        $this->assertNull($other->fresh()->customerProfile);
        $this->get(route('customer.profile.edit'))->assertOk()->assertSee('Customer Name')->assertSee('+61 400 123 456');
    }

    public function test_preferences_preserve_other_settings_and_marketing_opt_in_time(): void
    {
        $user = User::factory()->customer()->create();
        $user->customerProfile()->create(['preferences' => ['existing_setting' => 'keep']]);
        $input = ['action' => 'preferences', 'media_ready_email' => 1, 'expiry_reminders_email' => 0, 'marketing_email' => 1];
        $this->actingAs($user)->post(route('customer.profile.store'), $input)->assertRedirect();
        $profile = $user->fresh()->customerProfile;
        $consented = $profile->marketing_consent_at;
        $this->assertNotNull($consented);
        $this->assertSame(['existing_setting' => 'keep', 'media_ready_email' => true, 'expiry_reminders_email' => false], $profile->preferences);
        $this->travel(1)->day();
        $this->post(route('customer.profile.store'), $input)->assertRedirect();
        $this->assertTrue($consented->equalTo($profile->fresh()->marketing_consent_at));
        $this->post(route('customer.profile.store'), array_replace($input, ['marketing_email' => 0]))->assertRedirect();
        $this->assertNull($profile->fresh()->marketing_consent_at);
        $this->post(route('customer.profile.store'), ['action' => 'preferences', 'media_ready_email' => 'wrong'])->assertSessionHasErrors(['media_ready_email', 'expiry_reminders_email', 'marketing_email']);
    }

    public function test_dancer_editing_normalizes_aliases_and_rejects_cross_account_changes(): void
    {
        $user = User::factory()->customer()->create();
        $dancer = CustomerDancer::create(['user_id' => $user->id, 'name' => 'Original Name']);
        $other = User::factory()->customer()->create();
        $input = ['action' => 'dancer_update', 'dancer_uuid' => $dancer->uuid, 'name' => 'Updated Name',
            'relationship' => 'self', 'programme_names' => "  Stage Name \nStage Name\nAnother Name\n"];
        $this->actingAs($other)->post(route('customer.profile.store'), $input)->assertNotFound();
        $this->post(route('customer.profile.store'), ['action' => 'dancer_remove', 'dancer_uuid' => $dancer->uuid])->assertNotFound();
        $this->actingAs($user)->post(route('customer.profile.store'), $input)->assertRedirect();
        $this->assertSame('Updated Name', $dancer->fresh()->name);
        $this->assertSame('self', $dancer->fresh()->relationship);
        $this->assertSame(['Stage Name', 'Another Name'], $dancer->fresh()->programme_names);
        $this->get(route('customer.profile.edit', ['tab' => 'dancers']))->assertOk()->assertSee('Stage Name');
        $this->post(route('customer.profile.store'), array_replace($input, ['programme_names' => '']))->assertRedirect();
        $this->assertSame([], $dancer->fresh()->programme_names);
        CustomerDancer::create(['user_id' => $user->id, 'name' => 'Taken Name']);
        $this->post(route('customer.profile.store'), array_replace($input, ['name' => 'Taken Name']))->assertSessionHasErrors('name');
        $this->assertSame('Updated Name', $dancer->fresh()->name);
    }

    public function test_studio_names_cannot_be_saved_as_dancers_or_programme_aliases(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create(['name' => 'Harbour Dance Academy']);

        $this->actingAs($user)->post(route('customer.profile.store'), [
            'action' => 'dancer', 'name' => 'Harbour-Dance Academy',
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('customer_dancers', 0);

        $this->post(route('customer.profile.store'), [
            'action' => 'dancer', 'name' => 'Mia Hart', 'programme_names' => $studio->name,
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('customer_dancers', 0);
    }

    public function test_profile_removal_preserves_media_and_does_not_remove_another_accounts_links(): void
    {
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $studio = Studio::factory()->inactive()->create();
        CustomerStudio::create(['user_id' => $user->id, 'studio_id' => $studio->id]);
        $dancer = CustomerDancer::create(['user_id' => $user->id, 'name' => 'Remove Me']);
        $event = SchedulingEvent::create(['name' => 'Registered event', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        CustomerCompetitionRegistration::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id, 'access_verified_at' => now()]);
        $this->actingAs($other)->post(route('customer.profile.store'), ['action' => 'studio_remove', 'studio_uuid' => $studio->uuid])->assertNotFound();
        $this->actingAs($user)->get(route('customer.profile.edit', ['tab' => 'studios']))->assertOk()->assertSee($studio->name)->assertSee('Currently unavailable');
        $this->post(route('customer.profile.store'), ['action' => 'studio_remove', 'studio_uuid' => $studio->uuid])->assertRedirect();
        $this->post(route('customer.profile.store'), ['action' => 'dancer_remove', 'dancer_uuid' => $dancer->uuid])->assertRedirect();
        $this->assertDatabaseCount('customer_studios', 0);
        $this->assertDatabaseCount('customer_dancers', 0);
        $this->assertDatabaseCount('studios', 1);
        $this->assertDatabaseCount('customer_competition_registrations', 1);
        $this->get(route('customer.competitions.show', $event))->assertOk();
    }
}
