<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Services\TwoFactorAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_is_inactive_by_default(): void
    {
        $user = User::factory()->staff()->create(['email' => 'admin@dancepro.test', 'password' => 'test-password']);

        $this->actingAs($user)->get(route('account.security'))->assertNotFound();
        auth()->logout();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'test-password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_enabled_two_factor_can_be_configured_and_challenges_the_next_login(): void
    {
        config()->set('security.two_factor.enabled', true);
        $user = User::factory()->staff()->create(['email' => 'secure@dancepro.test', 'password' => 'test-password']);

        $this->actingAs($user)->post(route('two-factor.begin'), ['current_password' => 'test-password'])->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);
        $this->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect()
            ->assertSessionHas('two_factor_recovery_codes');
        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);

        auth()->logout();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'test-password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Authentication code');
        $this->post(route('two-factor.verify'), ['code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_enforcement_redirects_an_unconfigured_user_to_security_setup(): void
    {
        config()->set('security.two_factor.enabled', true);
        config()->set('security.two_factor.enforced', true);
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('account.security'));
    }

    public function test_recovery_code_is_single_use(): void
    {
        config()->set('security.two_factor.enabled', true);
        $user = User::factory()->staff()->create();
        app(TwoFactorAuthentication::class)->begin($user);
        $plainCodes = app(TwoFactorAuthentication::class)->confirm($user->refresh(), (new Google2FA)->getCurrentOtp($user->two_factor_secret));
        $this->assertNotEmpty($plainCodes);
        $this->assertTrue(app(TwoFactorAuthentication::class)->useRecoveryCode($user->refresh(), $plainCodes[0]));
        $this->assertFalse(app(TwoFactorAuthentication::class)->useRecoveryCode($user->refresh(), $plainCodes[0]));
    }
}
