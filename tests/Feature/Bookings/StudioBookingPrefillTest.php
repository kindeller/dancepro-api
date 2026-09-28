<?php

namespace Tests\Feature\Bookings;

use App\Features\Customers\Models\CustomerProfile;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Studios\Models\StudioContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioBookingPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_hub_opens_an_editable_booking_with_profile_details(): void
    {
        $user = User::factory()->customer()->create(['name' => 'Alex Director']);
        $studio = Studio::factory()->create(['name' => 'Test Dance Studio', 'contact_name' => 'Studio Director', 'contact_email' => 'office@example.test', 'contact_phone' => '0890000000']);
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => 'active', 'role' => 'director', 'activated_at' => now()]);
        StudioContact::create(['studio_id' => $studio->id, 'name' => 'Alex Director', 'emails' => [$user->email], 'phone' => '0400000000']);
        $url = route('concert-bookings.create', ['studio' => $studio->uuid]);
        $this->actingAs($user)->get(route('customer.studio-communications.index', $studio))
            ->assertOk()->assertSee('Make booking')->assertSee($url, false);
        $response = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame([
            'studio_name' => 'Test Dance Studio', 'contact_name' => 'Alex Director',
            'contact_email' => $user->email, 'contact_phone' => '0400000000',
        ], $response->viewData('prefill'));
        $response->assertSee('value="Test Dance Studio"', false)->assertSee('value="0400000000"', false);
        $this->withSession(['_old_input' => ['studio_name' => 'Corrected studio', 'contact_phone' => '0411111111']])
            ->get($url)->assertOk()->assertSee('value="Corrected studio"', false)->assertSee('value="0411111111"', false);
        $this->assertSame('Test Dance Studio', $studio->fresh()->name);
        $this->assertDatabaseCount('concert_bookings', 0);
    }

    public function test_prefill_does_not_expose_another_studio_or_revoked_access(): void
    {
        $studio = Studio::factory()->create();
        $url = route('concert-bookings.create', ['studio' => $studio->uuid]);
        $this->get($url)->assertForbidden();
        $user = User::factory()->customer()->create();
        $this->actingAs($user)->get($url)->assertForbidden();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => 'revoked', 'role' => 'director']);
        $this->get($url)->assertForbidden();
    }

    public function test_public_form_stays_available_and_account_details_are_used_when_no_studio_is_selected(): void
    {
        $this->get(route('concert-bookings.create'))->assertOk()->assertSee('value=""', false);
        $user = User::factory()->customer()->create(['name' => 'Profile Director']);
        CustomerProfile::factory()->create(['user_id' => $user->id, 'phone' => '0499999999']);
        $response = $this->actingAs($user)->get(route('concert-bookings.create'))->assertOk();
        $this->assertSame('Profile Director', $response->viewData('prefill')['contact_name']);
        $this->assertSame('0499999999', $response->viewData('prefill')['contact_phone']);
        $this->assertNull($response->viewData('prefill')['studio_name']);
    }
}
