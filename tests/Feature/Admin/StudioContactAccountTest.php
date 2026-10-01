<?php

namespace Tests\Feature\Admin;

use App\Features\Customers\Models\CustomerProfile;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Notifications\StudioAccountInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudioContactAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_search_finds_partial_name_email_and_account_id_and_excludes_inactive_accounts(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Taylor Studio', 'email' => 'taylor@studio.test']);
        CustomerProfile::create(['user_id' => $customer->id, 'phone' => '0400123456']);
        User::factory()->customer()->create(['name' => 'Taylor Inactive', 'is_active' => false]);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['Tayl', 'taylor@stud', substr($customer->uuid, 0, 12)] as $term) {
            $this->getJson(route('admin.customers.search', ['q' => $term]))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $customer->uuid)->assertJsonPath('data.0.phone', '0400123456');
        }
        $this->actingAs($customer)->getJson(route('admin.customers.search', ['q' => 'Taylor']))->assertForbidden();
    }

    public function test_saving_selected_staff_grants_verified_owner_access_and_does_not_repeat_invitations(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();
        $this->actingAs(User::factory()->admin()->create());
        $payload = $this->payload($customer, 'owner');
        $this->post(route('admin.studios.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $studio = Studio::where('name', 'Account Picker Studio')->firstOrFail();
        $this->assertSame($customer->id, $studio->contacts()->first()->user_id);
        $this->assertSame($customer->email, $studio->contact_email);
        $this->assertDatabaseHas('studio_account_accesses', ['studio_id' => $studio->id, 'user_id' => $customer->id, 'role' => 'owner', 'status' => 'active']);
        Notification::assertSentOnDemand(StudioAccountInvitation::class);
        Notification::assertCount(1);
        $this->put(route('admin.studios.update', $studio), $payload)->assertSessionHasNoErrors()->assertRedirect();
        Notification::assertCount(1);
        $this->get(route('admin.studios.edit', $studio))->assertOk()->assertSee($customer->uuid)->assertSee('Studio Hub access');
        $payload['contacts'][0]['access_role'] = 'director';
        $this->put(route('admin.studios.update', $studio), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('studio_account_accesses', ['studio_id' => $studio->id, 'user_id' => $customer->id, 'role' => 'director']);
    }

    public function test_unverified_accounts_stay_pending_and_contact_only_does_not_grant_access(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->unverified()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.studios.store'), $this->payload($customer, 'office_contact'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('studio_account_accesses', ['user_id' => $customer->id, 'status' => 'pending']);
        $other = User::factory()->customer()->create();
        $payload = $this->payload($other, null);
        $payload['name'] = 'Contact Only Studio';
        $this->post(route('admin.studios.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('studio_account_accesses', ['user_id' => $other->id]);
        $this->assertDatabaseHas('studio_contacts', ['user_id' => $other->id]);
        Notification::assertCount(1);
    }

    public function test_access_requires_valid_selected_customer_and_admin_permission(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();
        $payload = $this->payload($customer, 'owner');
        $this->actingAs($customer)->post(route('admin.studios.store'), $payload)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $payload['contacts'][0]['account_uuid'] = null;
        $this->post(route('admin.studios.store'), $payload)->assertSessionHasErrors('contacts.0.account_uuid');
        $payload['contacts'][0]['account_uuid'] = User::factory()->customer()->create(['is_active' => false])->uuid;
        $this->post(route('admin.studios.store'), $payload)->assertSessionHasErrors('contacts.0.account_uuid');
        $payload = $this->payload($customer, 'admin');
        $this->post(route('admin.studios.store'), $payload)->assertSessionHasErrors('contacts.0.access_role');
        $this->assertDatabaseCount('studios', 0);
        Notification::assertNothingSent();
    }

    private function payload(User $customer, ?string $role): array
    {
        return ['name' => 'Account Picker Studio', 'status' => 'active', 'contacts' => [[
            'name' => $customer->name, 'emails' => 'alternate@example.test', 'account_uuid' => $customer->uuid, 'access_role' => $role,
        ]]];
    }
}
