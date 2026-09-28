<?php

namespace Tests\Feature\Auth;

use App\Features\Customers\Support\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalAccountSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_switch_to_an_active_placeholder_account_without_a_password(): void
    {
        $current = User::factory()->customer()->create([
            'email' => 'current@example.test',
            'type' => UserType::Customer->value,
        ]);
        $target = User::factory()->customer()->create([
            'email' => 'target@example.test',
            'type' => UserType::Customer->value,
        ]);

        $response = $this->actingAs($current)->post(route('local.account.switch'), [
            'user_id' => $target->id,
        ]);

        $response->assertRedirect(route('customer.media.index'));
        $response->assertSessionHas('status', "Testing as {$target->name}.");
        $this->assertAuthenticatedAs($target);
        $this->assertNotNull($target->fresh()->last_login_at);
    }

    public function test_the_switcher_rejects_non_placeholder_accounts(): void
    {
        $current = User::factory()->customer()->create([
            'email' => 'current@example.test',
            'type' => UserType::Customer->value,
        ]);
        $realAccount = User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'type' => UserType::Customer->value,
        ]);

        $this->actingAs($current)->post(route('local.account.switch'), [
            'user_id' => $realAccount->id,
        ])->assertNotFound();

        $this->assertAuthenticatedAs($current);
    }

    public function test_switching_to_an_admin_opens_the_admin_dashboard_and_customer_pages_keep_an_admin_return_control(): void
    {
        $current = User::factory()->customer()->create([
            'email' => 'current@example.test',
            'type' => UserType::Customer->value,
        ]);
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.test',
        ]);

        $this->actingAs($current)->post(route('local.account.switch'), [
            'user_id' => $admin->id,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->get(route('studios.index'))
            ->assertOk()
            ->assertSee('Admin Hub')
            ->assertSee('href="'.route('admin.dashboard').'"', false);
    }

    public function test_the_switcher_requires_an_authenticated_session(): void
    {
        $target = User::factory()->customer()->create([
            'email' => 'target@example.test',
            'type' => UserType::Customer->value,
        ]);

        $this->post(route('local.account.switch'), [
            'user_id' => $target->id,
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
