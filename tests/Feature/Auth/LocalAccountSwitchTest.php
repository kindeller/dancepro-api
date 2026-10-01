<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Services\LocalAccountSwitcher;
use App\Features\Customers\Support\UserType;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalAccountSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_account_can_be_selected_without_changing_its_email(): void
    {
        $current = User::factory()->admin()->create(['email' => 'admin@example.test']);
        $owner = User::factory()->customer()->create(['name' => 'Rehearsal Owner', 'email' => 'owner@example.com']);
        StudioAccountAccess::create(['studio_id' => Studio::factory()->create()->id, 'user_id' => $owner->id,
            'email' => $owner->email, 'role' => 'owner', 'status' => 'active']);
        $this->assertSame($owner->id, app(LocalAccountSwitcher::class)->accounts()->get('studio')->first()['id']);
        $this->actingAs($current)->post(route('local.account.switch'), ['user_id' => $owner->id])
            ->assertRedirect(route('customer.studio-hub'));
        $this->assertAuthenticatedAs($owner);
        $this->assertSame('owner@example.com', $owner->fresh()->email);
        $owner->update(['is_active' => false]);
        $this->assertNull(app(LocalAccountSwitcher::class)->findAllowed($owner->id));
    }

    public function test_switching_remains_unavailable_outside_local_and_testing(): void
    {
        $owner = User::factory()->customer()->create(['email' => 'owner@example.com']);
        $this->app->instance('env', 'production');
        $this->assertTrue(app(LocalAccountSwitcher::class)->accounts()->isEmpty());
        $this->actingAs($owner)->withSession(['_token' => 'rehearsal-csrf'])
            ->post(route('local.account.switch'), ['user_id' => $owner->id, '_token' => 'rehearsal-csrf'])->assertForbidden();
    }

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

    public function test_new_accounts_appear_immediately_regardless_of_email_domain(): void
    {
        $current = User::factory()->customer()->create([
            'email' => 'current@example.test',
            'type' => UserType::Customer->value,
        ]);
        $switcher = app(LocalAccountSwitcher::class);
        $this->assertCount(1, $switcher->accounts()->flatten(1));
        $realAccount = User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'type' => UserType::Customer->value,
        ]);
        $this->assertCount(2, $switcher->accounts()->flatten(1));

        $this->actingAs($current)->post(route('local.account.switch'), [
            'user_id' => $realAccount->id,
        ])->assertRedirect(route('customer.media.index'));

        $this->assertAuthenticatedAs($realAccount);
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
