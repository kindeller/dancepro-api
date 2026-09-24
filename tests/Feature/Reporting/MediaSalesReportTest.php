<?php

namespace Tests\Feature\Reporting;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaSalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_filter_the_tabbed_sales_report(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create(['name' => 'Report Customer', 'email' => 'report@example.test']);
        $event = SchedulingEvent::create(['name' => 'Report Competition', 'event_type' => 'competition', 'event_date' => '2026-09-10']);
        $studio = Studio::factory()->create(['name' => 'Report Studio']);

        $this->paidOrder($customer, '2026-08-01', 4500, 'competition', $event->uuid, 'routine_photos');
        $this->paidOrder($customer, '2026-09-10', 1800, 'competition', $event->uuid, 'routine_video', 200, 100, 500);
        $other = User::factory()->customer()->create(['name' => 'Concert Customer', 'email' => 'concert@example.test']);
        $this->paidOrder($other, '2026-09-12', 1000, 'concert', $studio->uuid, 'concert_photo');
        Order::factory()->create(['status' => 'draft', 'metadata' => ['source' => 'customer_cart'], 'total_amount' => 9999]);
        CustomerCreditTransaction::create(['user_id' => $customer->id, 'amount' => 2000, 'kind' => 'gift', 'note' => 'Report fixture']);

        $this->actingAs($staff)->get(route('admin.media-sales.index', [
            'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'kind' => 'competition',
        ]))->assertOk()
            ->assertSee('Orders, customers and commerce performance')
            ->assertSee('Discounts &amp; credit', false)
            ->assertSee('$18.00')
            ->assertSee('1')
            ->assertDontSee('$45.00');

        $this->get(route('admin.media-sales.index', [
            'tab' => 'customers', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30',
        ]))->assertOk()->assertSee('Returning customers')->assertSee('Report Customer');

        $this->get(route('admin.media-sales.index', ['tab' => 'products']))
            ->assertOk()->assertSee('All routine photos')->assertSee('Routine video')->assertSee('Concert photo');
        $this->get(route('admin.media-sales.index', ['tab' => 'events']))
            ->assertOk()->assertSee('Report Competition')->assertSee('Report Studio');
        $this->get(route('admin.media-sales.index', ['tab' => 'discounts']))
            ->assertOk()->assertSee('Outstanding credit')->assertSee('$20.00')->assertSee('$5.00');
        $this->get(route('admin.media-sales.index', ['tab' => 'loyalty']))
            ->assertOk()->assertSee('Not enabled')->assertSee('No loyalty balances or rewards are being created.');
    }

    public function test_staff_can_export_filtered_paid_orders(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create(['email' => 'export@example.test']);
        $event = SchedulingEvent::create(['name' => 'Export Event', 'event_type' => 'competition', 'event_date' => '2026-09-10']);
        $this->paidOrder($customer, '2026-09-10', 4500, 'competition', $event->uuid, 'routine_photos');

        $response = $this->actingAs($staff)->get(route('admin.media-sales.export', ['kind' => 'competition']));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('export@example.test', $response->streamedContent());
        $this->assertStringContainsString('45', $response->streamedContent());
    }

    private function paidOrder(User $customer, string $date, int $total, string $kind, string $sourceUuid, string $product, int $bulk = 0, int $code = 0, int $credit = 0): Order
    {
        $order = Order::factory()->paid()->create([
            'user_id' => $customer->id, 'customer_email' => $customer->email, 'customer_name' => $customer->name,
            'subtotal_amount' => $total + $bulk + $code, 'total_amount' => $total, 'tax_amount' => intdiv($total, 11),
            'paid_at' => $date.' 12:00:00', 'placed_at' => $date.' 11:55:00',
            'metadata' => ['source' => 'customer_cart', 'kind' => $kind, 'source_uuid' => $sourceUuid,
                'discount_amount' => $bulk + $code, 'bulk_discount_amount' => $bulk,
                'discount_code_amount' => $code, 'account_credit_amount' => $credit],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id, 'media_asset_id' => null, 'media_collection_id' => null,
            'total_price_amount' => $total, 'unit_price_amount' => $total, 'metadata' => ['product' => $product],
        ]);

        return $order;
    }
}
