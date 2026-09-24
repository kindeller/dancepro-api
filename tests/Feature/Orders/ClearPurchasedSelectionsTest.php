<?php

namespace Tests\Feature\Orders;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClearPurchasedSelectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_competition_payment_clears_only_snapshotted_cart_items_and_their_favourites(): void
    {
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Purchased competition', 'event_type' => 'competition', 'event_date' => now()->toDateString()]);
        $item = (string) Str::uuid();
        $purchased = CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_package:'.$item, 'product' => 'routine_package',
            'item_uuid' => $item, 'in_cart' => true, 'is_favourite' => true]);
        $later = CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_video:'.$item, 'product' => 'routine_video',
            'item_uuid' => $item, 'in_cart' => true, 'is_favourite' => true]);
        $someoneElses = CustomerMediaSelection::create(['user_id' => $other->id, 'scheduling_event_id' => $event->id,
            'selection_key' => $purchased->selection_key, 'product' => 'routine_package',
            'item_uuid' => $item, 'in_cart' => true, 'is_favourite' => true]);
        $order = Order::factory()->for($user)->create(['metadata' => [
            'source' => 'customer_cart', 'kind' => 'competition', 'source_uuid' => $event->uuid,
            'cart_selection_keys' => [$purchased->selection_key], 'cart_selection_ids' => [$purchased->id],
        ]]);

        $order->update(['status' => OrderStatus::Pending]);
        $this->assertModelExists($purchased);
        $order->update(['status' => OrderStatus::Paid]);
        $this->assertModelExists($purchased);
        $order->update(['paid_at' => now()]);

        $this->assertModelMissing($purchased);
        $this->assertModelExists($later);
        $this->assertModelExists($someoneElses);
        $order->update(['status' => OrderStatus::Fulfilled]);
        $this->assertModelExists($later);
    }

    public function test_confirmed_concert_payment_clears_only_purchased_photos_from_cart_and_favourites(): void
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        $concert = Concert::factory()->create(['studio_id' => $studio->id]);
        $collection = MediaCollection::factory()->create(['concert_id' => $concert->id]);
        $boughtAsset = MediaAsset::factory()->create(['media_collection_id' => $collection->id]);
        $savedAsset = MediaAsset::factory()->create(['media_collection_id' => $collection->id]);
        $bought = CustomerConcertPhotoSelection::create(['user_id' => $user->id, 'studio_id' => $studio->id,
            'concert_id' => $concert->id, 'media_asset_id' => $boughtAsset->id, 'in_cart' => true, 'is_favourite' => true]);
        $saved = CustomerConcertPhotoSelection::create(['user_id' => $user->id, 'studio_id' => $studio->id,
            'concert_id' => $concert->id, 'media_asset_id' => $savedAsset->id, 'is_favourite' => true]);
        $order = Order::factory()->for($user)->create(['metadata' => [
            'source' => 'customer_cart', 'kind' => 'concert', 'source_uuid' => $studio->uuid,
            'cart_asset_uuids' => [$boughtAsset->uuid], 'cart_selection_ids' => [$bought->id],
        ]]);

        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        $this->assertModelMissing($bought);
        $this->assertModelExists($saved);
    }

    public function test_recreated_selection_after_draft_is_not_cleared_by_the_old_order(): void
    {
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Changed cart', 'event_type' => 'competition', 'event_date' => now()->toDateString()]);
        $item = (string) Str::uuid();
        $attributes = ['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_photos:'.$item, 'product' => 'routine_photos',
            'item_uuid' => $item, 'in_cart' => true, 'is_favourite' => true];
        $original = CustomerMediaSelection::create($attributes);
        $order = Order::factory()->for($user)->create(['metadata' => [
            'source' => 'customer_cart', 'kind' => 'competition', 'source_uuid' => $event->uuid,
            'cart_selection_keys' => [$original->selection_key], 'cart_selection_ids' => [$original->id],
        ]]);
        $original->delete();
        $replacement = CustomerMediaSelection::create($attributes);

        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        $this->assertModelExists($replacement);
    }
}
