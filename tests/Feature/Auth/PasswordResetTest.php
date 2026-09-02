<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@dancepro.test', 'password' => 'old-password']);
        $token = null;

        $this->get(route('password.request'))->assertOk()->assertSee('Reset your password');
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
    }

    public function test_reset_request_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'missing@dancepro.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account matches that email, a password reset link has been sent.');
    }

    public function test_password_reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        foreach (range(1, 3) as $attempt) {
            $this->post(route('password.email'), ['email' => 'limited-reset@dancepro.test'])
                ->assertRedirect()
                ->assertSessionHas('status');
        }

        $this->post(route('password.email'), ['email' => 'limited-reset@dancepro.test'])
            ->assertTooManyRequests();
    }

    public function test_password_reset_submissions_are_rate_limited(): void
    {
        $payload = [
            'token' => 'invalid-token',
            'email' => 'limited-submit@dancepro.test',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.update'), $payload)
                ->assertRedirect()
                ->assertSessionHasErrors('email');
        }

        $this->post(route('password.update'), $payload)
            ->assertTooManyRequests();
    }
}
