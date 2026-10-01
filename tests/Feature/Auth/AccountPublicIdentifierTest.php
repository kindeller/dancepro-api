<?php

namespace Tests\Feature\Auth;

use App\Features\Messaging\Services\MessageRecipients;
use App\Features\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountPublicIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_get_unique_public_uuids_and_old_account_urls_still_work(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $this->assertTrue(Str::isUuid($customer->uuid));
        $this->assertNotSame($admin->uuid, $customer->uuid);
        $this->assertSame($customer->uuid, $customer->getRouteKey());
        $this->actingAs($admin)->get(route('admin.customers.show', $customer))->assertOk()->assertSee($customer->uuid);
        $this->get('/admin/customers/'.$customer->id)->assertOk()->assertSee($customer->uuid);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $this->assertTrue(ctype_digit($order->displayNumber()));
        $this->assertSame($customer->id, $order->fresh()->user_id);
        $this->assertSame($customer->uuid, app(MessageRecipients::class)->search('customer', $order->displayNumber(), $admin)->sole()['reference']);
    }

    public function test_order_number_search_does_not_match_an_unrelated_internal_account_number(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $other = User::factory()->customer()->create(['id' => (int) $order->displayNumber()]);
        $results = app(MessageRecipients::class)->search('customer', $order->displayNumber(), $admin);
        $this->assertSame([$customer->uuid], $results->pluck('reference')->all());
        $this->actingAs($admin)->get(route('admin.customers.index', ['q' => $order->displayNumber()]))->assertOk()->assertSee($customer->name)
            ->assertViewHas('customers', fn ($customers) => $customers->pluck('id')->all() === [$customer->id]);
    }
}
