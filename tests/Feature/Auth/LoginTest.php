<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Services\TwoFactorAuthentication;
use App\Features\Auth\Support\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_receive_a_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'secret-password',
            'device_name' => 'Feature test',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged in.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'is_active'],
                ],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'abilities' => json_encode([
                TokenAbility::AccountRead->value,
                TokenAbility::CompetitionObjectsRead->value,
                TokenAbility::DownloadLinksManage->value,
            ]),
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_non_admin_token_receives_only_the_account_read_ability(): void
    {
        $user = User::factory()->crew()->create([
            'email' => 'crew@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'abilities' => json_encode([TokenAbility::AccountRead->value]),
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'staff@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This account is inactive.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_login_requires_two_factor_code_for_a_configured_user(): void
    {
        config()->set('security.two_factor.enabled', true);
        $user = $this->confirmedTwoFactorUser();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Two-factor authentication is required.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_login_accepts_a_valid_two_factor_code(): void
    {
        config()->set('security.two_factor.enabled', true);
        $user = $this->confirmedTwoFactorUser();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'two_factor_code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret),
        ])->assertOk()->assertJsonPath('message', 'Logged in.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_api_login_accepts_each_recovery_code_only_once(): void
    {
        config()->set('security.two_factor.enabled', true);
        [$user, $recoveryCodes] = $this->confirmedTwoFactorUser(withRecoveryCodes: true);

        $credentials = [
            'email' => $user->email,
            'password' => 'secret-password',
            'recovery_code' => $recoveryCodes[0],
        ];

        $this->postJson('/api/auth/login', $credentials)->assertOk();
        $this->postJson('/api/auth/login', $credentials)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The authentication or recovery code was not valid.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_enforced_two_factor_rejects_api_login_until_setup_is_complete(): void
    {
        config()->set('security.two_factor.enabled', true);
        config()->set('security.two_factor.enforced', true);
        $user = User::factory()->staff()->create([
            'email' => 'setup-required@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Two-factor authentication must be configured in the web account before API login.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_login_is_rate_limited_by_email_and_ip(): void
    {
        User::factory()->create([
            'email' => 'limited-api@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', [
                'email' => 'limited-api@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'limited-api@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_web_login_is_rate_limited_by_email_and_ip(): void
    {
        User::factory()->create([
            'email' => 'limited-web@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => 'limited-web@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => 'limited-web@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    private function confirmedTwoFactorUser(bool $withRecoveryCodes = false): User|array
    {
        $user = User::factory()->staff()->create([
            'email' => 'two-factor-api@example.com',
            'password' => Hash::make('secret-password'),
        ]);
        $twoFactor = app(TwoFactorAuthentication::class);
        $twoFactor->begin($user);
        $user->refresh();
        $recoveryCodes = $twoFactor->confirm($user, (new Google2FA)->getCurrentOtp($user->two_factor_secret));
        $user->refresh();

        return $withRecoveryCodes ? [$user, $recoveryCodes] : $user;
    }
}
