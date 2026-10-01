<?php

namespace Tests\Feature\Customers;

use App\Features\Crew\Actions\UpdateOwnCrewProfile;
use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use App\Shared\Support\AccountName;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_preserves_multiword_first_and_last_names_for_personalised_greetings(): void
    {
        Notification::fake();
        $this->get(route('customer.register'))->assertOk()->assertSee('name="first_name"', false)->assertSee('name="last_name"', false);
        $this->post(route('customer.register.store'), ['first_name' => ' Mary Jane ', 'last_name' => ' van der Berg ',
            'email' => 'mary@example.test', 'password' => 'long-local-password', 'password_confirmation' => 'long-local-password', 'policy_accepted' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect(route('customer.verification.notice'));
        $user = User::where('email', 'mary@example.test')->firstOrFail();
        $this->assertSame('Mary Jane', $user->first_name);
        $this->assertSame('van der Berg', $user->last_name);
        $this->assertSame('Mary Jane van der Berg', $user->name);
        $this->assertSame('Mary Jane', $user->emailGreetingName());
        $message = (new VerifyEmail)->toMail($user);
        $this->assertSame('Mary Jane', $message->viewData['name']);
    }

    public function test_legacy_names_are_split_and_later_explicit_corrections_remain_in_sync(): void
    {
        $this->assertSame(['first_name' => 'Jesstudio', 'last_name' => 'Ownerberg'], AccountName::split('Jesstudio Ownerberg'));
        $this->assertSame(['first_name' => 'Alex', 'last_name' => null], AccountName::split('Alex'));
        $user = User::factory()->customer()->create(['name' => 'Mary Jane van der Berg']);
        $this->assertSame('Mary', $user->first_name);
        $this->assertSame('Jane van der Berg', $user->last_name);
        $user->update(['first_name' => 'Mary Jane', 'last_name' => 'van der Berg']);
        $this->assertSame('Mary Jane van der Berg', $user->fresh()->name);
        $user->update(['name' => 'Alex Smith']);
        $this->assertSame('Alex', $user->fresh()->first_name);
        $this->assertSame('Smith', $user->fresh()->last_name);
    }

    public function test_customer_can_correct_name_parts_and_crew_nickname_does_not_replace_account_name(): void
    {
        $user = User::factory()->customer()->create(['name' => 'Mary Jane van der Berg']);
        $user->customerProfile()->create(['registration_source' => 'web', 'terms_accepted_at' => now(), 'privacy_accepted_at' => now(), 'policy_version' => config('customer.policy_version')]);
        $this->actingAs($user)->post(route('customer.profile.store'), ['action' => 'details', 'first_name' => 'Mary Jane', 'last_name' => 'van der Berg', 'preferred_name' => 'Mary', 'phone' => '0400000000', 'email' => $user->email])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Mary Jane', $user->fresh()->first_name);
        $crew = CrewProfile::factory()->create(['user_id' => $user->id, 'preferred_name' => 'MJ']);
        app(UpdateOwnCrewProfile::class)->execute($crew, ['preferred_name' => 'MJ', 'email' => $user->email]);
        $this->assertSame('Mary Jane van der Berg', $user->fresh()->name);
    }

    public function test_customer_crew_and_admin_profile_forms_all_offer_both_name_fields(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('customer.profile.edit'))->assertOk()
            ->assertSee('name="first_name"', false)->assertSee('name="last_name"', false);
        $crew = CrewProfile::factory()->create();
        $this->actingAs($crew->user)->get(route('crew.profile.edit'))->assertOk()
            ->assertSee('name="first_name"', false)->assertSee('name="last_name"', false);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([route('admin.customers.show', [$customer, 'tab' => 'settings']), route('admin.crew.edit', $crew), route('admin.crew.create')] as $url) {
            $this->get($url)->assertOk()->assertSee('name="first_name"', false)->assertSee('name="last_name"', false);
        }
    }
}
