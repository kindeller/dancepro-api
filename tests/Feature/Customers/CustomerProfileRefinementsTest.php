<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Notifications\AccountEmailChange;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Studios\Models\StudioContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerProfileRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_menu_badge_totals_missing_dancer_and_studio_details(): void
    {
        $user = User::factory()->customer()->create();
        $profile = route('customer.profile.edit', ['tab' => 'family']);
        $this->actingAs($user)->get($profile)->assertOk()
            ->assertSee('aria-label="2 profile actions required"', false)
            ->assertSee('aria-label="1 action required: Dancer not added"', false)
            ->assertSee('aria-label="1 action required: Studio not added"', false);

        $this->post(route('customer.family.store'), ['action' => 'manager', 'is_dancer' => true])->assertSessionHasNoErrors();
        $this->get($profile)->assertOk()
            ->assertSee('aria-label="1 profile actions required"', false)
            ->assertDontSee('aria-label="1 action required: Dancer not added"', false);
        $this->get(route('customer.media.index'))->assertOk()->assertSee('aria-label="1 profile actions required"', false);

        CustomerStudio::create(['user_id' => $user->id, 'studio_id' => Studio::factory()->create()->id, 'access_verified_at' => now()]);
        $this->get($profile)->assertOk()->assertDontSee('profile actions required')->assertDontSee('1 action required:');
        $this->post(route('customer.family.store'), ['action' => 'manager', 'is_dancer' => false])->assertSessionHasNoErrors();
        $this->get($profile)->assertOk()
            ->assertSee('aria-label="1 profile actions required"', false)
            ->assertSee('aria-label="1 action required: Dancer not added"', false)
            ->assertDontSee('aria-label="1 action required: Studio not added"', false);
    }

    public function test_details_are_prefilled_editable_and_required(): void
    {
        $user = User::factory()->customer()->create(['first_name' => 'Alex', 'last_name' => 'Smith']);
        $user->customerProfile()->create(['preferred_name' => 'Ali', 'phone' => '0400111222']);
        $this->actingAs($user)->get(route('customer.profile.edit'))->assertOk()
            ->assertSee('value="Alex"', false)->assertSee('value="Smith"', false)
            ->assertSee('value="Ali"', false)->assertSee('value="0400111222"', false)
            ->assertSee('name="email"', false)->assertDontSee('To change your account email, send us a message');
        $this->post(route('customer.profile.store'), ['action' => 'details'])->assertSessionHasErrors(['first_name', 'last_name', 'preferred_name', 'phone', 'email']);
    }

    public function test_studio_summary_has_completion_state_logo_and_collapsed_editing_with_shared_hub_button(): void
    {
        $user = User::factory()->customer()->create();
        $url = route('customer.profile.edit', ['tab' => 'studios']);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('My studio')->assertSee('Studio not added')->assertSee('Add/change studio');
        $studio = Studio::factory()->create(['name' => 'Test Studio', 'logo_path' => 'studio-logos/test.png']);
        CustomerStudio::create(['user_id' => $user->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        StudioAccountAccess::create(['user_id' => $user->id, 'studio_id' => $studio->id, 'email' => $user->email, 'role' => 'owner', 'status' => 'active']);
        $response = $this->get($url)->assertOk()->assertSee('Studio added')->assertSee('Test Studio logo')
            ->assertSee('class="app-switch-link studio-hub-link"', false)->assertDontSee('No studio added yet');
        $this->assertMatchesRegularExpression('/<details class="profile-studio-manager"\s*>/', $response->getContent());
    }

    public function test_email_change_requires_password_and_new_address_verification_then_preserves_studio_access(): void
    {
        Notification::fake();
        $user = User::factory()->customer()->create(['email' => 'old@example.test', 'password' => 'test-password']);
        $studio = Studio::factory()->create();
        $access = StudioAccountAccess::create(['user_id' => $user->id, 'studio_id' => $studio->id, 'email' => $user->email, 'role' => 'owner', 'status' => 'active']);
        $contact = StudioContact::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'name' => $user->name, 'emails' => [$user->email]]);
        $payload = $this->details($user) + ['current_password' => 'wrong'];
        $payload['email'] = 'new@example.test';
        $this->actingAs($user)->post(route('customer.profile.store'), $payload)->assertSessionHasErrors('current_password');
        $this->assertNull($user->fresh()->pending_email);
        $payload['current_password'] = 'test-password';
        $this->post(route('customer.profile.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('old@example.test', $user->fresh()->email);
        $this->assertSame('new@example.test', $user->fresh()->pending_email);
        $url = null;
        Notification::assertSentOnDemand(AccountEmailChange::class, function ($notification, $channels, $notifiable) use (&$url) {
            $url = $notification->confirmationUrl;

            return $notifiable->routeNotificationFor('mail') === 'new@example.test' && $url !== null;
        });
        $this->get($url)->assertRedirect(route('customer.profile.edit'));
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->pending_email);
        $this->assertSame('new@example.test', $access->fresh()->email);
        $this->assertSame('owner', $access->fresh()->role);
        $this->assertSame(['new@example.test'], $contact->fresh()->emails);
        Notification::assertSentOnDemand(AccountEmailChange::class, fn ($notification, $channels, $notifiable) => $notifiable->routeNotificationFor('mail') === 'old@example.test' && $notification->confirmationUrl === null);
        $this->get($url)->assertForbidden();
    }

    public function test_email_confirmation_cannot_be_used_by_another_account_or_after_expiry(): void
    {
        Notification::fake();
        $user = User::factory()->customer()->create(['password' => 'test-password']);
        $payload = $this->details($user);
        $payload['email'] = 'change@example.test';
        $payload['current_password'] = 'test-password';
        $this->actingAs($user)->post(route('customer.profile.store'), $payload)->assertSessionHasNoErrors();
        $url = null;
        Notification::assertSentOnDemand(AccountEmailChange::class, function ($notification) use (&$url) {
            $url = $notification->confirmationUrl;

            return true;
        });
        $other = User::factory()->customer()->create();
        $this->actingAs($other)->withSession(['password_hash_web' => $other->getAuthPassword()])->get($url)->assertForbidden();
        $this->travel(61)->minutes();
        $this->actingAs($user)->withSession(['password_hash_web' => $user->getAuthPassword()])->get($url)->assertForbidden();
        $this->assertNotSame('change@example.test', $user->fresh()->email);
    }

    private function details(User $user): array
    {
        return ['action' => 'details', 'first_name' => 'Alex', 'last_name' => 'Smith', 'preferred_name' => 'Alex', 'phone' => '0400000000', 'email' => $user->email];
    }
}
