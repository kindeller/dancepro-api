<?php

namespace Tests\Feature\Reporting;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerEngagementEvent;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Features\Reporting\Services\GalleryEngagementReport;
use App\Features\Reporting\Services\MediaSalesReport;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $concert = Concert::factory()->create(['studio_id' => $studio->id, 'name' => 'Report Concert']);

        $this->paidOrder($customer, '2026-08-01', 4500, 'competition', $event->uuid, 'routine_photos');
        $septemberOrder = $this->paidOrder($customer, '2026-09-10', 1800, 'competition', $event->uuid, 'routine_video', 200, 100, 500);
        $other = User::factory()->customer()->create(['name' => 'Concert Customer', 'email' => 'concert@example.test']);
        $this->paidOrder($other, '2026-09-12', 1000, 'concert', $studio->uuid, 'concert_photo', eventUuid: $concert->uuid);
        Order::factory()->create(['status' => 'draft', 'metadata' => ['source' => 'customer_cart'], 'total_amount' => 9999]);
        CustomerCreditTransaction::create(['user_id' => $customer->id, 'amount' => 2000, 'kind' => 'gift', 'note' => 'Report fixture']);

        $this->actingAs($staff)->get(route('admin.media-sales.index', [
            'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'kind' => 'competition',
        ]))->assertOk()
            ->assertSee('Sales, customer engagement, discounts and download activity')
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
            ->assertOk()->assertSee('Report Competition')->assertSee('Report Concert');
        $this->get(route('admin.media-sales.index', ['tab' => 'discounts']))
            ->assertOk()->assertSee('Current outstanding credit')->assertSee('$20.00')->assertSee('$5.00');
        $this->get(route('admin.media-sales.index', ['tab' => 'loyalty']))
            ->assertOk()->assertSee('Not enabled')->assertSee('No loyalty balances or rewards are being created.');
        $this->get(route('admin.media-sales.index', ['tab' => 'orders', 'customer' => 'Order '.$septemberOrder->displayNumber()]))
            ->assertOk()->assertSee($septemberOrder->displayNumber())->assertSee('report@example.test');
        $matchingOrders = app(MediaSalesReport::class)->query(['customer' => 'Order '.$septemberOrder->displayNumber()])->pluck('id');
        $this->assertSame([$septemberOrder->id], $matchingOrders->all());

        foreach (['overview', 'customers', 'products', 'events', 'discounts', 'downloads', 'loyalty', 'orders'] as $tab) {
            $this->get(route('admin.media-sales.index', ['tab' => $tab]))->assertOk();
        }
    }

    public function test_staff_can_export_filtered_paid_orders(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create(['email' => 'export@example.test']);
        $event = SchedulingEvent::create(['name' => 'Export Event', 'event_type' => 'competition', 'event_date' => '2026-09-10']);
        $order = $this->paidOrder($customer, '2026-09-10', 4500, 'competition', $event->uuid, 'routine_photos');

        $response = $this->actingAs($staff)->get(route('admin.media-sales.export', ['kind' => 'competition']));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('export@example.test', $response->streamedContent());
        $this->assertStringContainsString($order->displayNumber(), $response->streamedContent());
        $this->assertStringNotContainsString($order->uuid, $response->streamedContent());
        $this->assertStringContainsString('45', $response->streamedContent());
    }

    public function test_event_engagement_links_to_the_gallery_and_lists_account_and_guest_visitors(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create(['name' => 'Gallery Customer', 'email' => 'gallery-customer@example.test']);
        $event = SchedulingEvent::create(['name' => 'Engagement Competition', 'event_type' => 'competition', 'event_date' => '2026-09-20']);
        $itemUuid = (string) Str::uuid();

        CustomerEngagementEvent::create([
            'user_id' => $customer->id, 'event_kind' => 'competition', 'event_uuid' => $event->uuid,
            'action' => 'viewed', 'occurred_at' => '2026-09-20 09:00:00',
        ]);
        CustomerMediaSelection::create([
            'user_id' => $customer->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine:'.$itemUuid, 'product' => 'routine_photos', 'item_uuid' => $itemUuid,
            'is_favourite' => true, 'in_cart' => true,
        ]);
        $guest = GuestGalleryAccess::create([
            'gallery_type' => 'competition', 'scheduling_event_id' => $event->id,
            'email_hash' => hash('sha256', 'guest@example.test'), 'email' => 'guest@example.test',
            'expires_at' => now()->addMonth(), 'last_used_at' => '2026-09-20 10:00:00',
        ]);
        $guest->selections()->create([
            'scheduling_event_id' => $event->id, 'selection_key' => 'photo:'.Str::uuid(),
            'product' => 'photo', 'item_uuid' => $itemUuid, 'photo_uuid' => Str::uuid(),
            'is_favourite' => true, 'in_cart' => false,
        ]);
        $order = $this->paidOrder($customer, '2026-09-20', 4500, 'competition', $event->uuid, 'routine_photos');

        $report = app(GalleryEngagementReport::class);
        $eventRow = $report->events([])['rows']->firstWhere('uuid', $event->uuid);
        $this->assertSame(2, $eventRow['visitors']);
        $this->assertSame(2, $eventRow['with_favourites']);
        $this->assertSame(1, $eventRow['with_cart']);
        $this->assertSame(1, $eventRow['with_orders']);
        $this->assertSame(1, $eventRow['without_orders']);
        $this->assertSame(1, $eventRow['orders']);
        $this->assertSame(4500, $eventRow['sales']);

        $activity = $report->activity('competition', $event->uuid, [], false);
        $this->assertSame([
            'visitors' => 2,
            'with_favourites' => 2,
            'with_cart' => 1,
            'with_orders' => 1,
            'without_orders' => 1,
            'orders' => 1,
            'sales' => 4500,
        ], $activity['summary']);

        $events = $this->actingAs($staff)->get(route('admin.media-sales.index', ['tab' => 'events']));
        $events->assertOk()->assertSee('Engagement Competition')
            ->assertSee(route('admin.competition-gallery.show', $event), false)
            ->assertSee(route('admin.media-sales.events.activity', ['kind' => 'competition', 'eventUuid' => $event->uuid]), false)
            ->assertSee('Gallery visitors')->assertSee('With cart items')->assertSee('Without paid orders');

        $activityRoute = route('admin.media-sales.events.activity', ['kind' => 'competition', 'eventUuid' => $event->uuid]);
        $this->get($activityRoute)->assertOk()
            ->assertSee('Gallery Customer')->assertSee('gallery-customer@example.test')
            ->assertSee('guest@example.test')->assertSee('Guest visitor')
            ->assertSee('Order '.$order->displayNumber())->assertSee('Export visitors CSV');
        $this->get($activityRoute.'?activity=no_order')->assertOk()
            ->assertSee('guest@example.test')->assertSee('0 paid orders', false);

        $export = $this->get(route('admin.media-sales.events.activity.export', [
            'kind' => 'competition', 'eventUuid' => $event->uuid,
        ]));
        $export->assertOk()->assertDownload();
        $this->assertStringContainsString('guest@example.test', $export->streamedContent());
        $this->assertStringContainsString($order->displayNumber(), $export->streamedContent());
    }

    public function test_concert_events_are_grouped_beneath_unique_studio_totals(): void
    {
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create(['name' => 'Grouped Dance Studio']);
        $junior = Concert::factory()->create(['studio_id' => $studio->id, 'name' => 'Junior Showcase', 'event_date' => '2026-09-12']);
        $senior = Concert::factory()->create(['studio_id' => $studio->id, 'name' => 'Senior Showcase', 'event_date' => '2026-09-13']);
        $firstCustomer = User::factory()->customer()->create(['name' => 'Studio Parent', 'email' => 'parent@example.test']);
        $secondCustomer = User::factory()->customer()->create(['name' => 'Studio Guest', 'email' => 'guest-parent@example.test']);

        $select = function (User $customer, Concert $concert, bool $favourite, bool $cart): void {
            $collection = MediaCollection::factory()->create([
                'concert_id' => $concert->id,
                'competition_id' => null,
                'media_type' => MediaType::Photo,
            ]);
            $asset = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
            CustomerConcertPhotoSelection::create([
                'user_id' => $customer->id,
                'studio_id' => $concert->studio_id,
                'concert_id' => $concert->id,
                'media_asset_id' => $asset->id,
                'is_favourite' => $favourite,
                'in_cart' => $cart,
            ]);
        };
        $select($firstCustomer, $junior, true, true);
        $select($firstCustomer, $senior, true, false);
        $select($secondCustomer, $senior, false, true);
        $order = $this->paidOrder($firstCustomer, '2026-09-12', 1500, 'concert', $studio->uuid, 'concert_photo', eventUuid: $junior->uuid);

        $report = app(GalleryEngagementReport::class)->events(['kind' => 'concert']);
        $studioRow = $report['rows']->first(fn (array $row): bool => $row['row_type'] === 'studio' && $row['uuid'] === $studio->uuid);
        $this->assertSame(2, $studioRow['event_count']);
        $this->assertSame(2, $studioRow['visitors']);
        $this->assertSame(1, $studioRow['with_favourites']);
        $this->assertSame(2, $studioRow['with_cart']);
        $this->assertSame(1, $studioRow['with_orders']);
        $this->assertSame(1, $studioRow['without_orders']);
        $this->assertSame(1, $studioRow['orders']);
        $this->assertSame(1500, $studioRow['sales']);

        $eventRows = $report['rows']->where('parent_uuid', $studio->uuid)->keyBy('uuid');
        $this->assertSame(1, $eventRows[$junior->uuid]['visitors']);
        $this->assertSame(1, $eventRows[$junior->uuid]['with_orders']);
        $this->assertSame(2, $eventRows[$senior->uuid]['visitors']);
        $this->assertSame(0, $eventRows[$senior->uuid]['with_orders']);

        $eventsPage = $this->actingAs($staff)->get(route('admin.media-sales.index', ['tab' => 'events', 'kind' => 'concert']));
        $eventsPage->assertOk()->assertSee('Grouped Dance Studio')->assertSee('All 2 concert events')
            ->assertSee('Junior Showcase')->assertSee('Senior Showcase')->assertSee('Concert studio')->assertSee('Concert event');

        $activityUrl = route('admin.media-sales.studios.activity', $studio);
        $this->get($activityUrl)->assertOk()->assertSee('Studio Parent')->assertSee('Studio Guest')
            ->assertSee('Order '.$order->displayNumber())->assertSee('Concert studio galleries');
        $this->get(route('admin.media-sales.studios.activity.export', $studio))->assertOk()->assertDownload();
    }

    private function paidOrder(User $customer, string $date, int $total, string $kind, string $sourceUuid, string $product, int $bulk = 0, int $code = 0, int $credit = 0, ?string $eventUuid = null): Order
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
            'total_price_amount' => $total, 'unit_price_amount' => $total, 'metadata' => ['product' => $product]
                + ($eventUuid ? ['event_kind' => $kind, 'event_uuid' => $eventUuid] : []),
        ]);

        return $order;
    }
}
