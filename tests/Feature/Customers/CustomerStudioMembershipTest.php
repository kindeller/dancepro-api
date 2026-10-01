<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Models\CustomerStudioRequest;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerStudioMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_code_adds_membership_and_released_concert_photos_without_studio_hub_access(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create(['code' => 'EDS']);
        $this->actingAs($user)->post(route('customer.profile.store'), ['action' => 'studio', 'studio_uuid' => $studio->uuid, 'studio_code' => ' eds '])
            ->assertRedirect(route('customer.profile.edit', ['tab' => 'studios']))->assertSessionHasNoErrors();
        $link = CustomerStudio::sole();
        $this->assertNotNull($link->membership_confirmed_at);
        $this->assertNotNull($link->access_verified_at);
        $this->get(route('customer.profile.edit', ['tab' => 'studios']))->assertOk()->assertSee('Studio added')->assertSee($studio->name)->assertDontSee('<select name="studio_uuid"', false);
        $this->get(route('customer.profile.studio', $studio))->assertOk();
        $this->assertDatabaseCount('studio_account_accesses', 0);
    }

    public function test_gallery_code_cannot_substitute_for_studio_code_and_existing_gallery_access_survives(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create(['code' => 'EDS']);
        $studio->forceFill(['gallery_code_hash' => Hash::make('PRIVATE')])->save();
        $input = ['action' => 'studio', 'studio_uuid' => $studio->uuid, 'studio_code' => 'PRIVATE'];
        $this->actingAs($user)->post(route('customer.profile.store'), $input)->assertSessionHasErrors('studio_code')->assertSessionMissing('_old_input.studio_code');
        $this->assertDatabaseCount('customer_studios', 0);
        CustomerStudio::create(['user_id' => $user->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $input['studio_code'] = 'EDS';
        $this->post(route('customer.profile.store'), $input)->assertSessionHasNoErrors();
        $this->assertNotNull(CustomerStudio::sole()->access_verified_at);
    }

    public function test_live_search_is_authenticated_limited_and_does_not_expose_codes_or_contacts(): void
    {
        $studio = Studio::factory()->create(['name' => 'Energy Dance Studio', 'code' => 'SECRET', 'contact_email' => 'private@example.test']);
        Studio::factory()->create(['name' => 'Energy Archived', 'status' => 'archived']);
        $url = route('customer.profile.studios.search', ['q' => 'energ']);
        $this->getJson($url)->assertUnauthorized();
        $user = User::factory()->customer()->create();
        $this->actingAs($user)->getJson($url)->assertOk()->assertJsonPath('data.0.uuid', $studio->uuid)->assertJsonCount(1, 'data')
            ->assertDontSee('SECRET')->assertDontSee('private@example.test');
        $this->getJson(route('customer.profile.studios.search', ['q' => 'e']))->assertJsonCount(0, 'data');
        $this->getJson(route('customer.profile.studios.search', ['q' => '%%']))->assertJsonCount(0, 'data');
    }

    public function test_unlisted_studio_is_pending_deduplicated_and_admin_can_create_it_and_keep_customer_attached(): void
    {
        $customer = User::factory()->customer()->create();
        $input = ['action' => 'studio_request', 'studio_name' => 'A New Dance Studio', 'studio_location' => 'Perth'];
        $this->actingAs($customer)->post(route('customer.profile.store'), $input)->assertSessionHasNoErrors();
        $this->post(route('customer.profile.store'), $input)->assertSessionHasNoErrors();
        $submission = CustomerStudioRequest::sole();
        $this->assertDatabaseCount('customer_studios', 0);
        $this->get(route('customer.profile.edit', ['tab' => 'studios']))->assertSee('Awaiting review')->assertSee('A New Dance Studio')->assertDontSee('1 action required: Studio not added');
        $this->get(route('admin.studio-requests.show', $submission))->assertForbidden();
        $admin = User::factory()->admin()->create();
        $queue = $this->actingAs($admin)->get(route('admin.studio-requests.index'))->assertOk();
        $this->assertSame(2, substr_count($queue->getContent(), 'data-notification-key="studio_requests"'));
        $queue->assertSee('data-notification-key="hub_management"', false);
        $this->get(route('admin.studio-requests.show', $submission))->assertOk()->assertSee('Create a new studio')->assertSee('A New Dance Studio');
        $payload = ['decision' => 'create', 'name' => 'Confirmed Dance Studio', 'code' => 'CDS', 'status' => 'active', 'description' => 'Studio details', 'is_school' => true];
        $this->post(route('admin.studio-requests.store', $submission), $payload)->assertSessionHasNoErrors();
        $submission->refresh();
        $this->assertSame('approved', $submission->status);
        $this->assertSame($admin->id, $submission->reviewed_by_user_id);
        $this->assertTrue($submission->studio->is_school);
        $this->assertSame('Studio details', $submission->studio->description);
        $this->assertDatabaseHas('customer_studios', ['user_id' => $customer->id, 'studio_id' => $submission->studio_id]);
        $this->assertNotNull(CustomerStudio::sole()->access_verified_at);
        $this->actingAs($customer)->get(route('customer.profile.edit', ['tab' => 'studios']))->assertOk()->assertSee('Confirmed Dance Studio')->assertSee('Studio added')->assertDontSee('Awaiting review');
    }

    public function test_admin_matching_existing_studio_does_not_create_duplicate_and_cannot_review_twice(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->post(route('customer.profile.store'), ['action' => 'studio_request', 'studio_name' => 'Energy Dance']);
        $submission = CustomerStudioRequest::sole();
        $studio = Studio::factory()->create();
        $payload = ['decision' => 'match', 'studio_uuid' => $studio->uuid];
        $this->post(route('admin.studio-requests.store', $submission), $payload)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.studio-requests.store', $submission), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('studios', 1);
        $this->assertSame($studio->id, $submission->fresh()->studio_id);
        $this->post(route('admin.studio-requests.store', $submission), $payload)->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('customer_studios', 1);
    }

    public function test_decline_explains_reason_and_customer_can_resubmit_without_seeing_other_customers_requests(): void
    {
        $customer = User::factory()->customer()->create();
        $payload = ['action' => 'studio_request', 'studio_name' => 'Unknown Studio'];
        $this->actingAs($customer)->post(route('customer.profile.store'), $payload)->assertSessionHasNoErrors();
        $submission = CustomerStudioRequest::sole();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.studio-requests.store', $submission), ['decision' => 'decline'])->assertSessionHasErrors('review_note');
        $this->post(route('admin.studio-requests.store', $submission), ['decision' => 'decline', 'review_note' => 'Please provide the full studio name.'])->assertSessionHasNoErrors();
        $this->actingAs($customer)->get(route('customer.profile.edit', ['tab' => 'studios']))->assertSee('Please provide the full studio name.');
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.profile.edit', ['tab' => 'studios']))->assertDontSee('Unknown Studio');
        $this->actingAs($customer)->post(route('customer.profile.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('pending', $submission->fresh()->status);
        $this->assertNull($submission->fresh()->review_note);
    }
}
