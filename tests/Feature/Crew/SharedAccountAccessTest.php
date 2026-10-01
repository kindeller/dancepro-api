<?php

namespace Tests\Feature\Crew;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Services\CustomerAccountIntelligence;
use App\Features\Customers\Services\MergeCustomerAccounts;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_switch_between_media_and_their_hub_without_admin_access(): void
    {
        $crew = User::factory()->crew()->create();
        CrewProfile::factory()->for($crew)->create();

        $this->actingAs($crew)->get(route('customer.media.index'))->assertOk()
            ->assertSee('Crew Hub')->assertSee(route('crew.availability.index'))
            ->assertDontSee('Admin Hub');
        $this->get(route('crew.availability.index'))->assertOk()
            ->assertSee('My Media')->assertSee(route('customer.media.index'))
            ->assertDontSee('Admin Hub');
        $this->get(route('admin.crew.create'))->assertForbidden();
        $this->get(route('customer.studio-communications.index', Studio::factory()->create()))->assertForbidden();
    }

    public function test_existing_customer_can_be_given_crew_access_without_replacing_their_account(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $original = $customer->refresh()->getAttributes();
        $studio = Studio::factory()->create();
        StudioAccountAccess::create([
            'studio_id' => $studio->id, 'user_id' => $customer->id, 'email' => $customer->email,
            'role' => 'director', 'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('admin.crew.create'))->assertOk()->assertSee('Enable My Hub');
        $this->post(route('admin.crew.store'), ['existing_email' => strtoupper($customer->email)])
            ->assertRedirect();
        $customer->refresh();
        $this->assertSame($original, $customer->getAttributes());
        $this->assertTrue($customer->canAccessCrew());
        $this->assertTrue($customer->canAccessStudioHub());
        $this->assertFalse($customer->canAccessAdmin());
        Notification::assertNothingSent();

        $this->actingAs($customer)->get(route('customer.media.index'))->assertOk()
            ->assertSee('Crew Hub')->assertSee('Studio Hub');
        $this->get(route('crew.availability.index'))->assertRedirect(route('crew.profile.edit'));
        $this->get(route('crew.profile.edit'))->assertOk()->assertSee('My Media');

        $this->actingAs($admin)->put(route('admin.crew.update', $customer->crewProfile), [
            'preferred_name' => $customer->name, 'email' => $customer->email,
            'phone' => '0400000000', 'commencement_date' => '2026-09-28', 'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('customer', $customer->fresh()->type);
        $this->assertSame($original['password'], $customer->fresh()->password);
    }

    public function test_enabling_crew_rejects_duplicates_inactive_accounts_and_customer_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = CrewProfile::factory()->create()->user;
        $inactive = User::factory()->customer()->inactive()->create();
        foreach ([$crew->email, $inactive->email, 'missing@example.test'] as $email) {
            $this->actingAs($admin)->post(route('admin.crew.store'), ['existing_email' => $email])
                ->assertSessionHasErrors('existing_email');
        }
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->post(route('admin.crew.store'), ['existing_email' => $customer->email])->assertForbidden();
        $this->get(route('customer.media.index'))->assertOk()->assertDontSee('My Hub');
        $this->get(route('crew.profile.edit'))->assertForbidden();
        $this->assertFalse($inactive->canAccessCustomer());
        $this->assertFalse($inactive->canAccessCrew());
    }

    public function test_customer_merge_cannot_disconnect_an_existing_crew_account(): void
    {
        $customer = User::factory()->customer()->create();
        CrewProfile::factory()->for($customer)->create();
        $target = User::factory()->customer()->create();
        $this->expectException(ValidationException::class);
        app(MergeCustomerAccounts::class)->execute(
            $customer, $target, User::factory()->admin()->create(), $target->email, 'Duplicate account'
        );
    }

    public function test_customer_anonymisation_cannot_leave_linked_crew_records_behind(): void
    {
        $customer = User::factory()->customer()->create();
        CrewProfile::factory()->for($customer)->create();
        $this->expectException(ValidationException::class);
        app(CustomerAccountIntelligence::class)->anonymize(
            $customer, User::factory()->admin()->create(), $customer->email, 'Privacy request'
        );
    }

    public function test_crew_still_need_verified_email_to_use_media(): void
    {
        $crew = User::factory()->crew()->unverified()->create();
        CrewProfile::factory()->for($crew)->create();
        $this->actingAs($crew)->get(route('customer.media.index'))
            ->assertRedirect(route('customer.verification.notice'));
    }
}
