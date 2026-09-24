<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function input(array $overrides = []): array
    {
        return array_replace(['name' => 'Test Customer', 'email' => 'new@example.test',
            'password' => 'local-test-password', 'password_confirmation' => 'local-test-password', 'policy_accepted' => '1'], $overrides);
    }

    public function test_customer_entry_pages_and_guest_redirect_are_separate_from_staff(): void
    {
        $this->get(route('customer.media.index'))->assertRedirect(route('customer.login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('customer.login'))->assertOk()->assertSee('Welcome back')->assertSee(route('customer.register'), false)->assertSee(route('password.request'), false);
        $this->get(route('customer.register'))->assertOk()->assertSee('Create your account');
    }

    public function test_signup_requires_policy_acceptance_and_email_verification(): void
    {
        Notification::fake();
        $this->post(route('customer.register.store'), $this->input(['policy_accepted' => null]))->assertSessionHasErrors('policy_accepted');
        $this->post(route('customer.register.store'), $this->input(['email' => ' NEW@EXAMPLE.TEST ',
            'is_admin' => true, 'type' => 'admin', 'email_verified_at' => now(), 'terms_accepted_at' => now()]))
            ->assertRedirect(route('customer.verification.notice'));
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('customer', $user->type);
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('local-test-password', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertSame('web', $user->customerProfile->registration_source);
        $this->assertNotNull($user->customerProfile->terms_accepted_at);
        $this->assertNotNull($user->customerProfile->privacy_accepted_at);
        $this->assertSame(config('customer.policy_version'), $user->customerProfile->policy_version);
        $this->assertNull($user->customerProfile->marketing_consent_at);
        $this->assertDatabaseCount('customer_competition_registrations', 0);
        $this->get(route('customer.media.index'))->assertRedirect(route('customer.verification.notice'));
        $this->get(route('admin.dashboard'))->assertForbidden();
        Notification::assertSentTo($user, VerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect(route('customer.media.index'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get(route('customer.media.index'))->assertOk();
    }

    public function test_signup_validates_passwords_without_flashing_them(): void
    {
        $this->post(route('customer.register.store'), $this->input(['password' => 'short']))
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->post(route('customer.register.store'), $this->input(['password' => str_repeat('é', 40), 'password_confirmation' => str_repeat('é', 40)]))
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('customer_profiles', 0);
    }

    public function test_existing_web_customer_completes_policy_onboarding_without_reaccepting_marketing(): void
    {
        $user = User::factory()->customer()->create();
        CustomerProfile::create(['user_id' => $user->id, 'registration_source' => 'web']);
        $this->actingAs($user)->get(route('customer.media.index'))->assertRedirect(route('customer.onboarding.edit'));
        $this->post(route('customer.onboarding.store'), [])->assertSessionHasErrors('policy_accepted');
        $this->post(route('customer.onboarding.store'), ['policy_accepted' => '1'])->assertRedirect(route('customer.media.index'));
        $this->assertNull($user->customerProfile->marketing_consent_at);
        $this->assertSame(config('customer.policy_version'), $user->customerProfile->fresh()->policy_version);
        $this->get(route('customer.media.index'))->assertOk();
    }

    public function test_existing_and_deleted_accounts_cannot_be_replaced_by_signup(): void
    {
        $user = User::factory()->create(['email' => 'new@example.test']);
        $this->post(route('customer.register.store'), $this->input())->assertSessionHasErrors('email');
        $user->delete();
        $this->post(route('customer.register.store'), $this->input())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customer_profiles', 0);
    }

    public function test_customer_sign_in_uses_existing_login_and_inactive_account_checks(): void
    {
        $user = User::factory()->customer()->create(['email' => 'new@example.test', 'password' => 'local-test-password']);
        $this->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'local-test-password'])->assertRedirect(route('customer.media.index'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('customer.login'));
        $user->update(['is_active' => false]);
        $this->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'local-test-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
