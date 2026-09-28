<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMediaProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_prompts_link_to_real_profile_forms_and_saved_details_enable_inputs(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        $studio->forceFill(['gallery_code_hash' => Hash::make('GALLERY-TEST')])->save();
        $this->actingAs($user)->get(route('customer.media.index'))->assertOk()
            ->assertSee('Add media')->assertSee('Add a dancer in My Profile')->assertSee('Add your studio in My Profile')
            ->assertSee(route('customer.profile.edit', ['tab' => 'dancers']), false)
            ->assertSee(route('customer.profile.edit', ['tab' => 'studios']), false)
            ->assertDontSee('name="concert_code"', false)->assertDontSee('<select name="event_uuid"', false);
        $this->get(route('customer.profile.edit', ['tab' => 'studios']))->assertOk()->assertSee($studio->name);
        foreach ([['action' => 'dancer', 'name' => 'Jessica Smith'], ['action' => 'studio', 'studio_uuid' => $studio->uuid, 'studio_gallery_code' => 'GALLERY-TEST']] as $input) {
            $this->post(route('customer.profile.store'), $input + ['user_id' => 999])->assertRedirect();
            $this->post(route('customer.profile.store'), $input)->assertRedirect();
        }
        $this->assertDatabaseCount('customer_dancers', 1);
        $this->assertDatabaseCount('customer_studios', 1);
        $this->assertDatabaseHas('customer_dancers', ['user_id' => $user->id, 'name' => 'Jessica Smith']);
        $this->get(route('customer.media.index'))->assertOk()->assertSee('<select name="event_uuid"', false)
            ->assertSee('Event media access code')->assertSee('name="year"', false)->assertSee('Video access code')
            ->assertSee(route('customer.profile.studio', $studio), false)->assertDontSee('Add now');
        $this->get(route('customer.profile.studio', $studio))->assertOk()->assertSee('not available yet');
        Concert::factory()->published()->create(['studio_id' => $studio->id, 'external_gallery_url' => 'https://photos.example.test/studio-gallery']);
        $this->get(route('customer.profile.studio', $studio))->assertOk()->assertSee('View/order photos')->assertSee('https://photos.example.test/studio-gallery', false);
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.profile.edit', ['tab' => 'dancers']))->assertOk()->assertDontSee('Jessica Smith');
        $this->get(route('customer.profile.studio', $studio))->assertForbidden();
    }

    public function test_video_code_is_scoped_to_registered_studio_and_selected_year(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        $right = Concert::factory()->published()->passwordProtected('test-video-code')->create(['studio_id' => $studio->id, 'event_date' => '2026-06-01']);
        Concert::factory()->published()->passwordProtected('test-video-code')->create(['studio_id' => $studio->id, 'event_date' => '2025-06-01']);
        Concert::factory()->published()->passwordProtected('test-video-code')->create(['event_date' => '2026-06-01']);
        $this->actingAs($user)->post(route('customer.media.concerts.store'), ['year' => 2026, 'concert_code' => 'test-video-code'])->assertSessionHasErrors('concert_code');
        CustomerStudio::create(['user_id' => $user->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $this->post(route('customer.media.concerts.store'), ['concert_code' => 'test-video-code'])->assertSessionHasErrors('year');
        $this->post(route('customer.media.concerts.store'), ['year' => 2026, 'concert_code' => 'test-video-code'])->assertRedirect();
        $this->assertDatabaseCount('concert_access_grants', 1);
        $this->assertDatabaseHas('concert_access_grants', ['user_id' => $user->id, 'concert_id' => $right->id]);
        $this->get(route('customer.media.index'))->assertSee('2026')->assertSee('2025');
    }

    public function test_profile_validation_permissions_and_dancer_prerequisite(): void
    {
        $this->get(route('customer.profile.edit'))->assertRedirect(route('customer.login'));
        $this->actingAs(User::factory()->customer()->create());
        $this->post(route('customer.profile.store'), ['action' => 'dancer', 'name' => '   '])->assertSessionHasErrors('name');
        $inactive = Studio::factory()->inactive()->create();
        $this->post(route('customer.profile.store'), ['action' => 'studio', 'studio_uuid' => $inactive->uuid])->assertSessionHasErrors('studio_uuid');
        $this->post(route('customer.competitions.store'), ['event_uuid' => (string) Str::uuid(), 'login_code' => 'TEST-CODE'])->assertSessionHasErrors(['login_code' => 'Add a dancer in My Profile first.']);
        $this->assertDatabaseCount('customer_dancers', 0);
        $this->assertDatabaseCount('customer_studios', 0);
        $this->actingAs(User::factory()->crew()->create())->get(route('customer.profile.edit'))->assertForbidden();
        $this->post(route('customer.profile.store'), ['action' => 'dancer', 'name' => 'No access'])->assertForbidden();
    }
}
