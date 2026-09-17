<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Support\ApiTokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_receive_a_sanctum_token(): void
    {
        $this->freezeTime();
        config(['auth.staff_token_ttl_minutes' => 43200]);
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
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame(ApiTokenAbility::staffAbilities(), $user->tokens()->firstOrFail()->abilities);
        $this->assertNotNull($user->tokens()->firstOrFail()->expires_at);
        $this->assertSame(now()->addDays(30)->timestamp, $user->tokens()->firstOrFail()->expires_at->timestamp);
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

    public function test_customer_cannot_receive_a_staff_api_token(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'secret-password',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This account cannot use staff API access.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
