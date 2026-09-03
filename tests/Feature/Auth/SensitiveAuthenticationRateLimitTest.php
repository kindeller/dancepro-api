<?php

namespace Tests\Feature\Auth;

use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensitiveAuthenticationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_authenticated_password_checks_are_rate_limited(): void
    {
        $user = User::factory()->crew()->create(['password' => 'current-password']);
        CrewProfile::factory()->for($user)->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($user)->put(route('crew.profile.password'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])->assertSessionHasErrors('current_password');
        }

        $this->actingAs($user)->put(route('crew.profile.password'), [
            'current_password' => 'incorrect-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertStatus(429);
    }

    public function test_all_password_confirmed_routes_use_the_sensitive_authentication_limiter(): void
    {
        foreach ([
            'two-factor.begin',
            'two-factor.recovery-codes',
            'two-factor.disable',
            'crew.profile.password',
            'crew.contracts.sign',
        ] as $routeName) {
            $middleware = app('router')->getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

            $this->assertContains('throttle:sensitive-auth', $middleware, "Route [{$routeName}] is not rate limited.");
        }
    }
}
