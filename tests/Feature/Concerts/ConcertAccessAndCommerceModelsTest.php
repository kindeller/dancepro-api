<?php

namespace Tests\Feature\Concerts;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Concerts\Support\ConcertAccessGrantSource;
use App\Features\Concerts\Support\ConcertAccessGrantStatus;
use App\Features\Concerts\Support\ConcertAccessMethod;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Support\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcertAccessAndCommerceModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_and_registered_concert_access_can_be_recorded(): void
    {
        $concert = Concert::factory()->create();
        $customer = User::factory()->create(['type' => 'customer']);
        $grant = ConcertAccessGrant::create([
            'concert_id' => $concert->id,
            'email' => $customer->email,
            'source' => ConcertAccessGrantSource::Invitation,
            'status' => ConcertAccessGrantStatus::Active,
        ]);

        ConcertAccess::create([
            'concert_id' => $concert->id,
            'access_method' => ConcertAccessMethod::Password,
            'accessed_at' => now(),
        ]);
        $grant->update(['user_id' => $customer->id, 'claimed_at' => now(), 'status' => ConcertAccessGrantStatus::Claimed]);
        ConcertAccess::create([
            'concert_id' => $concert->id,
            'user_id' => $customer->id,
            'concert_access_grant_id' => $grant->id,
            'access_method' => ConcertAccessMethod::SavedAccess,
            'accessed_at' => now(),
        ]);

        $this->assertCount(2, $concert->accesses);
        $this->assertTrue($grant->fresh()->user->is($customer));
    }

    public function test_order_item_preserves_a_snapshot_and_resolves_its_current_asset(): void
    {
        $asset = MediaAsset::factory()->create();
        $order = Order::create(['status' => OrderStatus::Draft, 'currency' => 'AUD']);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'media_collection_id' => $asset->media_collection_id,
            'media_asset_id' => $asset->id,
            'snapshot_storage_disk' => $asset->storage_disk,
            'snapshot_storage_key' => $asset->storage_key,
        ]);

        $oldKey = $asset->storage_key;
        $asset->update(['storage_key' => 'archive/'.Str::uuid().'/IMG_0001.jpg']);

        $this->assertSame($oldKey, $item->snapshot_storage_key);
        $this->assertSame($asset->fresh()->storage_key, $item->mediaAsset->fresh()->storage_key);
        $this->assertTrue($order->items->first()->is($item));
    }

    public function test_download_relationships_are_optional_and_null_when_domain_records_are_deleted(): void
    {
        $asset = MediaAsset::factory()->create();
        $collection = $asset->collection;
        $concert = $collection->concert;
        $link = DownloadLink::factory()->create([
            'concert_id' => $concert->id,
            'media_collection_id' => $collection->id,
            'media_asset_id' => $asset->id,
            'status' => DownloadLinkStatus::ACTIVE,
        ]);

        $plainLink = DownloadLink::factory()->create();
        $this->assertNull($plainLink->concert_id);

        $asset->forceDelete();
        $collection->forceDelete();
        $concert->forceDelete();

        $link->refresh();
        $this->assertNull($link->concert_id);
        $this->assertNull($link->media_collection_id);
        $this->assertNull($link->media_asset_id);
    }
}
