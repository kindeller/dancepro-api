<?php

namespace Tests\Feature\Orders;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Services\ActivateOrderMediaEntitlement;
use App\Features\Orders\Services\AuthorizePurchasedMediaDownload;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class OrderMediaEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_and_video_periods_start_separately_and_never_restart(): void
    {
        $paidAt = CarbonImmutable::parse('2026-09-22 10:00:00');
        $order = Order::factory()->paid()->create(['paid_at' => $paidAt]);
        $photoCollection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $videoCollection = MediaCollection::factory()->create(['media_type' => MediaType::Video]);
        $photo = MediaAsset::factory()->create(['media_collection_id' => $photoCollection->id, 'media_type' => MediaType::Photo]);
        $video = MediaAsset::factory()->create(['media_collection_id' => $videoCollection->id, 'media_type' => MediaType::Video]);
        $photoItem = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $photoCollection->id, 'media_asset_id' => null]);
        $videoItem = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $videoCollection->id, 'media_asset_id' => null]);
        $activate = app(ActivateOrderMediaEntitlement::class);

        $photos = $activate->execute($photoItem, 'photo', collect([$photo]), $paidAt->subDay());
        $pending = $activate->execute($videoItem, 'video', collect());
        $this->assertTrue($photos->ready_at->equalTo($paidAt));
        $this->assertTrue($photos->expires_at->equalTo($paidAt->addYear()));
        $this->assertNull($pending->ready_at);
        $this->assertNull($pending->expires_at);

        $videoReady = $paidAt->addDays(20);
        $videos = $activate->execute($videoItem, 'video', collect([$video]), $videoReady);
        $this->assertTrue($videos->ready_at->equalTo($videoReady));
        $this->assertTrue($videos->expires_at->equalTo($videoReady->addDays(60)));
        $this->assertSame([$video->id], $videos->assets->pluck('id')->all());
        $again = $activate->execute($videoItem, 'video', collect([$video]), $videoReady->addMonth());
        $this->assertTrue($again->expires_at->equalTo($videoReady->addDays(60)));
        $this->assertDatabaseCount('order_media_entitlements', 2);
    }

    public function test_unpaid_and_foreign_media_cannot_gain_entitlements(): void
    {
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $otherCollection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $foreign = MediaAsset::factory()->create(['media_collection_id' => $otherCollection->id, 'media_type' => MediaType::Photo]);
        $draft = Order::factory()->create();
        $item = OrderItem::factory()->create(['order_id' => $draft->id, 'media_collection_id' => $collection->id, 'media_asset_id' => null]);
        $activate = app(ActivateOrderMediaEntitlement::class);
        $this->expectException(ValidationException::class);
        $activate->execute($item, 'photo', collect([$foreign]));
    }

    public function test_paid_order_cannot_claim_an_asset_from_another_collection(): void
    {
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $foreignCollection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $foreign = MediaAsset::factory()->create(['media_collection_id' => $foreignCollection->id, 'media_type' => MediaType::Photo]);
        $order = Order::factory()->paid()->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $collection->id, 'media_asset_id' => null]);

        $this->expectException(ValidationException::class);
        app(ActivateOrderMediaEntitlement::class)->execute($item, 'photo', collect([$foreign]));
    }

    public function test_ready_assets_must_match_the_order_time_snapshot(): void
    {
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $purchased = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $later = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $order = Order::factory()->paid()->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $collection->id,
            'media_asset_id' => null, 'metadata' => ['asset_uuids' => [$purchased->uuid], 'media_pending' => false]]);

        $this->expectException(ValidationException::class);
        app(ActivateOrderMediaEntitlement::class)->execute($item, 'photo', collect([$purchased, $later]));
    }

    public function test_paid_pending_package_video_can_wait_without_a_source_or_expiry(): void
    {
        $order = Order::factory()->paid()->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => null,
            'media_asset_id' => null, 'snapshot_storage_disk' => null, 'snapshot_storage_key' => null,
            'item_type' => 'video', 'metadata' => ['asset_uuids' => [], 'media_pending' => true]]);

        $entitlement = app(ActivateOrderMediaEntitlement::class)->execute($item, 'video', collect());

        $this->assertNull($entitlement->ready_at);
        $this->assertNull($entitlement->expires_at);
        $this->assertSame([], $entitlement->assets->all());
    }

    public function test_download_authorization_uses_the_frozen_asset_set_and_expiry(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $owner->id]);
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $purchased = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $later = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $collection->id, 'media_asset_id' => null]);
        $entitlement = app(ActivateOrderMediaEntitlement::class)->execute($item, 'photo', collect([$purchased]));
        $authorize = app(AuthorizePurchasedMediaDownload::class);
        $this->assertSame($owner->id, $entitlement->orderItem->order->user_id);
        $this->assertSame([$purchased->id], $entitlement->assets->pluck('id')->all());

        $this->assertTrue($authorize->execute($owner, $entitlement, $purchased)->is($purchased));
        foreach ([[$stranger, $purchased], [$owner, $later]] as [$user, $asset]) {
            try {
                $authorize->execute($user, $entitlement, $asset);
                $this->fail('Unowned or unpurchased media was authorized.');
            } catch (NotFoundHttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }

        $entitlement->update(['expires_at' => now()->subSecond()]);
        try {
            $authorize->execute($owner, $entitlement, $purchased);
            $this->fail('Expired media was authorized.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
    }
}
