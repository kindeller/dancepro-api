<?php

namespace Tests\Feature\Orders;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Payments\Models\PaymentAttempt;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_review_products_source_totals_and_payment_history(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create(['name' => 'Order Customer', 'email' => 'order@example.test']);
        $event = SchedulingEvent::create(['name' => 'Spring Dance Challenge', 'event_type' => 'competition', 'event_date' => '2026-09-10']);
        $order = Order::factory()->paid()->create([
            'user_id' => $customer->id, 'customer_name' => $customer->name, 'customer_email' => $customer->email,
            'subtotal_amount' => 5000, 'total_amount' => 4000, 'tax_amount' => 364,
            'metadata' => ['source' => 'customer_cart', 'kind' => 'competition', 'source_uuid' => $event->uuid,
                'discount_amount' => 500, 'bulk_discount_amount' => 500, 'account_credit_amount' => 500,
                'total_before_account_credit' => 4500, 'payment_amount' => 4000],
        ]);
        OrderItem::factory()->create(['order_id' => $order->id, 'snapshot_display_name' => '12 Years Lyrical - Ava Example',
            'snapshot_filename' => 'DP100001.JPG', 'item_type' => 'photo', 'quantity' => 1,
            'unit_price_amount' => 5000, 'total_price_amount' => 4500, 'tax_amount' => 364,
            'metadata' => ['product' => 'routine_photos']]);
        PaymentAttempt::create(['order_id' => $order->id, 'user_id' => $customer->id, 'provider' => 'braintree',
            'idempotency_key' => fake()->uuid(), 'amount' => 4000, 'currency' => 'AUD', 'status' => 'approved',
            'provider_transaction_id' => 'txn-example-1', 'processed_at' => now()]);

        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Order '.$order->displayNumber())
            ->assertSee('Spring Dance Challenge')->assertSee('Order Customer')->assertSee('DP100001.JPG')
            ->assertSee('Bulk discount')->assertSee('Account credit')->assertSee('txn-example-1')
            ->assertSee('Amount collected')->assertSee('$40.00');
    }

    public function test_order_numbers_are_short_sequential_and_accept_message_friendly_formats(): void
    {
        $first = Order::factory()->create();
        $second = Order::factory()->create();

        $this->assertSame((string) (100000 + $first->id), $first->displayNumber());
        $this->assertSame((int) $first->displayNumber() + 1, (int) $second->displayNumber());
        $this->assertSame($first->id, Order::idFromDisplayNumber($first->displayNumber()));
        $this->assertSame($first->id, Order::idFromDisplayNumber('#'.$first->displayNumber()));
        $this->assertSame($first->id, Order::idFromDisplayNumber('Order '.$first->displayNumber()));
        $this->assertNull(Order::idFromDisplayNumber($first->uuid));
    }
}
