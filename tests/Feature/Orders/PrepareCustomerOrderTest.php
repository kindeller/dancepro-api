<?php

namespace Tests\Feature\Orders;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Services\CompetitionOrderCatalogue;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Customers\Services\CustomerConcertPhotoCart;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaDiscountCode;
use App\Features\Media\Models\MediaPriceList;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Services\PrepareCustomerOrder;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepareCustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_package_has_a_frozen_photo_item_and_a_separate_waiting_video_source(): void
    {
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Order event', 'event_type' => 'competition', 'event_date' => now()->toDateString()]);
        $itemUuid = (string) Str::uuid();
        $selection = CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_package:'.$itemUuid, 'product' => 'routine_package',
            'item_uuid' => $itemUuid, 'in_cart' => true]);
        $photoUuid = (string) Str::uuid();
        $included = CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'photo:'.$photoUuid, 'product' => 'photo', 'item_uuid' => $itemUuid,
            'photo_uuid' => $photoUuid, 'in_cart' => true, 'is_favourite' => true]);
        $collection = MediaCollection::factory()->forCompetition($event->id)->create(['media_type' => MediaType::Photo]);
        $photo = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);

        $this->mock(CustomerCompetitionAccess::class)->shouldReceive('allows')->andReturn(true);
        $this->mock(CustomerCompetitionCart::class)->shouldReceive('forEvent')->andReturn([
            'cart' => collect([$selection, $included]), 'priced' => collect([$selection]), 'lineAmounts' => [$selection->id => 4500],
            'subtotal' => 4500, 'total' => 4500,
        ]);
        $this->mock(CompetitionOrderCatalogue::class)->shouldReceive('readyRoutine')->andReturn([
            'collection' => $collection, 'assets' => collect([$photo]),
        ]);

        $prepare = app(PrepareCustomerOrder::class);
        $order = $prepare->competition($user, $event);
        $again = $prepare->competition($user, $event);

        $this->assertSame($order->id, $again->id);
        $this->assertSame(4500, $order->subtotal_amount);
        $this->assertSame(4500, $order->total_amount);
        $this->assertSame(['photo', 'video'], $order->items->pluck('item_type')->all());
        $this->assertSame([2700, 1800], $order->items->pluck('total_price_amount')->all());
        $this->assertSame([$photo->uuid], $order->items[0]->metadata['asset_uuids']);
        $this->assertSame([], $order->items[1]->metadata['asset_uuids']);
        $this->assertSame([$included->selection_key, $selection->selection_key], $order->metadata['cart_selection_keys']);
        $this->assertSame([$selection->id, $included->id], $order->metadata['cart_selection_ids']);
        $this->assertNull($order->items[1]->snapshot_storage_key);
        $this->assertDatabaseCount('orders', 1);

        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        $this->assertModelMissing($selection);
        $this->assertModelMissing($included);
    }

    public function test_a_changed_cart_creates_a_new_draft_without_altering_the_old_snapshot(): void
    {
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Order event', 'event_type' => 'competition', 'event_date' => now()->toDateString()]);
        $itemUuid = (string) Str::uuid();
        $selection = CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_photos:'.$itemUuid, 'product' => 'routine_photos',
            'item_uuid' => $itemUuid, 'in_cart' => true]);
        $collection = MediaCollection::factory()->forCompetition($event->id)->create(['media_type' => MediaType::Photo]);
        $first = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $second = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);

        $this->mock(CustomerCompetitionAccess::class)->shouldReceive('allows')->andReturn(true);
        $this->mock(CustomerCompetitionCart::class)->shouldReceive('forEvent')->andReturn([
            'priced' => collect([$selection]), 'lineAmounts' => [$selection->id => 4000],
            'subtotal' => 4000, 'total' => 4000,
        ]);
        $this->mock(CompetitionOrderCatalogue::class)->shouldReceive('readyRoutine')
            ->andReturn(['collection' => $collection, 'assets' => collect([$first])],
                ['collection' => $collection, 'assets' => collect([$first, $second])]);

        $prepare = app(PrepareCustomerOrder::class);
        $original = $prepare->competition($user, $event);
        $changed = $prepare->competition($user, $event);

        $this->assertNotSame($original->id, $changed->id);
        $this->assertSame('cancelled', $original->fresh()->status->value);
        $this->assertSame([$first->uuid], $original->items[0]->metadata['asset_uuids']);
        $this->assertSame([$first->uuid, $second->uuid], $changed->items[0]->metadata['asset_uuids']);
    }

    public function test_code_reduces_bulk_qualifying_spend_before_the_order_is_allocated(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        $concert = Concert::factory()->create(['studio_id' => $studio->id]);
        $collection = MediaCollection::factory()->create(['concert_id' => $concert->id]);
        $selections = collect(range(1, 10))->map(function () use ($collection, $concert): CustomerConcertPhotoSelection {
            $asset = MediaAsset::factory()->create(['media_collection_id' => $collection->id]);
            $selection = new CustomerConcertPhotoSelection;
            $selection->setRelation('asset', $asset);
            $selection->setRelation('concert', $concert);

            return $selection;
        });
        $this->mock(CustomerConcertPhotoCart::class)->shouldReceive('forStudio')->andReturn([
            'cart' => $selections, 'subtotal' => 10000, 'bulk_eligible_subtotal' => 10000,
            'lineAmounts' => [null => 1000], 'principalPrice' => false, 'total' => 9500,
        ]);

        MediaDiscountCode::create(['code' => 'CONCERT10', 'percent' => 10, 'enabled' => true]);
        $order = app(PrepareCustomerOrder::class)->concertPhotos($user, $studio, 'concert10');

        $this->assertSame(10, $order->items->count());
        $this->assertSame(10000, $order->subtotal_amount);
        $this->assertSame(9000, $order->total_amount);
        $this->assertSame(9000, $order->items->sum('total_price_amount'));
        $this->assertSame(1000, $order->metadata['discount_amount']);
        $this->assertSame(0, $order->metadata['bulk_discount_amount']);
        $this->assertSame('CONCERT10', $order->metadata['discount_code']);
        $this->assertSame(1000, $order->metadata['discount_code_amount']);
        $this->assertCount(10, $order->metadata['cart_asset_uuids']);
        $this->assertTrue($order->items->every(fn ($item) => count($item->metadata['asset_uuids']) === 1));
    }

    public function test_a_fixed_code_can_move_a_cart_below_the_bulk_threshold(): void
    {
        $user = User::factory()->customer()->create();
        $staff = User::factory()->staff()->create();
        $event = SchedulingEvent::create(['name' => 'Fixed code event', 'event_type' => 'competition', 'event_date' => now()]);
        $itemUuid = (string) Str::uuid();
        $selection = CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_photos:'.$itemUuid, 'product' => 'routine_photos', 'item_uuid' => $itemUuid, 'in_cart' => true]);
        $collection = MediaCollection::factory()->forCompetition($event->id)->create(['media_type' => MediaType::Photo]);
        $photo = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $this->mock(CustomerCompetitionAccess::class)->shouldReceive('allows')->andReturn(true);
        $this->mock(CustomerCompetitionCart::class)->shouldReceive('forEvent')->andReturn([
            'cart' => collect([$selection]), 'priced' => collect([$selection]), 'lineAmounts' => [$selection->id => 12000],
            'subtotal' => 12000, 'bulk_eligible_subtotal' => 12000, 'total' => 11400,
        ]);
        $this->mock(CompetitionOrderCatalogue::class)->shouldReceive('readyRoutine')->andReturn([
            'collection' => $collection, 'assets' => collect([$photo]),
        ]);
        MediaDiscountCode::create(['code' => 'ONCE40', 'discount_type' => 'fixed', 'percent' => null,
            'amount' => 4000, 'enabled' => true, 'ends_at' => now()->endOfMonth(), 'max_redemptions' => 1]);
        app(CustomerCredits::class)->grant($user, 3000, $staff, 'Customer service credit');

        $order = app(PrepareCustomerOrder::class)->competition($user, $event, 'once40', true);

        $this->assertSame(12000, $order->subtotal_amount);
        $this->assertSame(5000, $order->total_amount);
        $this->assertSame(5000, $order->paymentAmount());
        $this->assertSame(455, $order->tax_amount);
        $this->assertSame(455, $order->items->sum('tax_amount'));
        $this->assertSame(8000, $order->items->sum('total_price_amount'));
        $this->assertSame(5000, $order->items->sum(fn ($item): int => $item->metadata['net_after_account_credit']));
        $this->assertSame(1000, $order->items->first()->tax_rate_basis_points);
        $this->assertSame(4000, $order->metadata['discount_code_amount']);
        $this->assertSame(0, $order->metadata['bulk_discount_amount']);
        $this->assertSame(8000, $order->metadata['total_before_account_credit']);
        $this->assertSame(3000, $order->metadata['account_credit_amount']);

        MediaPriceList::query()->where('gallery_type', 'competition')->where('is_default', true)
            ->firstOrFail()->update(['taxable_products' => []]);
        $gstFreeOrder = app(PrepareCustomerOrder::class)->competition($user, $event, 'once40');

        $this->assertNotSame($order->id, $gstFreeOrder->id);
        $this->assertSame(0, $gstFreeOrder->tax_amount);
        $this->assertSame(0, $gstFreeOrder->items->first()->tax_rate_basis_points);
    }
}
