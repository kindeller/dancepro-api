<?php

namespace Tests\Feature\Auth;

use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedWebSignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_page_uses_customer_design_and_account_wording(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('Welcome back')
            ->assertSee('Sign in to access your DancePro account.')
            ->assertSee('images/brand/dancepro-media-heading.png', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee(route('customer.register'), false)
            ->assertSee(route('password.request'), false)
            ->assertDontSee('Customer sign in')
            ->assertDontSee('>Staff</a>', false);
    }

    public function test_legacy_link_keeps_pending_gallery_and_intended_destination(): void
    {
        $this->withSession([
            'publicity_join' => ['name' => 'Test concert'],
            'url.intended' => route('publicity.join.complete'),
        ])->get(route('customer.login'))->assertRedirect(route('login'));

        $this->get(route('login'))->assertOk()->assertSee('Test concert')
            ->assertSessionHas('url.intended', route('publicity.join.complete'));
    }

    public function test_legacy_failed_submission_keeps_errors_and_email_at_shared_page(): void
    {
        $this->followingRedirects()->from(route('customer.login'))->post(route('customer.login.store'), [
            'email' => 'missing@example.test',
            'password' => 'invalid-password',
        ])->assertOk()
            ->assertSee('The provided credentials could not be verified.')
            ->assertSee('missing@example.test')
            ->assertDontSee('invalid-password');
    }

    public function test_all_protected_web_areas_use_the_same_sign_in_page(): void
    {
        foreach (['customer.media.index', 'crew.availability.index', 'admin.dashboard'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_shared_sign_in_routes_accounts_to_their_default_areas(): void
    {
        $customer = User::factory()->customer()->create();
        $crew = User::factory()->crew()->create();
        CrewProfile::factory()->for($crew)->create();
        $admin = User::factory()->admin()->create();

        foreach ([[$customer, 'customer.media.index'], [$crew, 'crew.availability.index'], [$admin, 'admin.dashboard']] as [$user, $route]) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($route));
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }

    public function test_shared_sign_in_preserves_intended_page_and_does_not_grant_admin_access(): void
    {
        $user = User::factory()->customer()->create();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertForbidden();
    }
}
