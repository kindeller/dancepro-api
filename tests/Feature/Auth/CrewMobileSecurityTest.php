<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CrewMobileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_list_and_password_confirm_revocation_of_a_lost_device(): void
    {
        $user = $this->completeCrewUser();
        $oldToken = $this->login($user, 'Lost iPhone');
        $currentToken = $this->login($user, 'Current iPhone');

        $response = $this->withToken($currentToken)->getJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $lostDevice = collect($response->json('data'))->firstWhere('name', 'Lost iPhone');

        $this->assertIsArray($lostDevice);
        $this->assertFalse($lostDevice['current']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $lostDevice['id']);
        $this->assertStringNotContainsString($oldToken, $response->getContent());
        $this->assertStringNotContainsString($currentToken, $response->getContent());

        $this->withToken($currentToken)->deleteJson('/api/v1/auth/devices/'.$lostDevice['id'], [
            'password' => 'wrong-password',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($currentToken)->deleteJson('/api/v1/auth/devices/'.$lostDevice['id'], [
            'password' => 'secret-password',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($currentToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_crew_cannot_revoke_another_users_device(): void
    {
        $user = $this->completeCrewUser();
        $other = $this->completeCrewUser();
        $currentToken = $this->login($user, 'Current iPhone');
        $otherToken = $this->login($other, 'Other iPhone');
        $otherDevice = $this->withToken($otherToken)->getJson('/api/v1/auth/devices')->json('data.0.id');

        $this->app['auth']->forgetGuards();
        $this->withToken($currentToken)->deleteJson('/api/v1/auth/devices/'.$otherDevice, [
            'password' => 'secret-password',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_expired_mobile_token_is_rejected(): void
    {
        config(['security.mobile_token_expiration' => 1]);
        $user = $this->completeCrewUser();
        $token = $this->login($user, 'iPhone');

        $this->travel(2)->minutes();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->travelBack();
    }

    public function test_account_token_cannot_access_crew_mobile_routes(): void
    {
        $user = $this->completeCrewUser();
        Sanctum::actingAs($user, [TokenAbility::AccountRead->value]);

        $this->getJson('/api/v1/profile')->assertForbidden();
    }

    public function test_sensitive_mobile_routes_reject_direct_unauthenticated_requests(): void
    {
        foreach (['profile', 'invoices', 'documents', 'training'] as $endpoint) {
            $this->getJson('/api/v1/'.$endpoint)->assertUnauthorized();
        }
    }

    public function test_mobile_api_responses_are_not_stored_by_shared_http_caches(): void
    {
        $user = $this->completeCrewUser();
        $token = $this->login($user, 'iPhone');

        $this->withToken($token)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('pragma', 'no-cache');
    }

    public function test_forgot_password_is_public_and_enumeration_safe(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_crew_can_set_up_confirm_and_manage_two_factor_authentication(): void
    {
        config(['security.two_factor.enabled' => true]);
        $user = $this->completeCrewUser();
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        $setup = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/auth/two-factor', ['password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.recovery_codes', []);
        $secret = $setup->json('data.secret');
        $this->assertNotEmpty($setup->json('data.otpauth_url'));
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/auth/two-factor/confirm', ['code' => $code])
            ->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/auth/two-factor/recovery-codes', ['password' => 'secret-password'])
            ->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->deleteJson('/api/v1/auth/two-factor', ['password' => 'secret-password'])
            ->assertOk();
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    private function login(User $user, string $device): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => $device,
        ])->assertOk()->json('data.token');
    }

    private function completeCrewUser(): User
    {
        $user = User::factory()->crew()->create([
            'password' => Hash::make('secret-password'),
            'onboarding_completed_at' => now(),
        ]);
        CrewProfile::factory()->for($user)->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => now()->addYear()->toDateString(),
        ]);

        return $user->refresh();
    }
}
