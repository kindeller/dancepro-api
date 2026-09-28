<?php

namespace App\Features\Orders\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderMediaAssetDownload;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use Illuminate\Support\Collection;

class AdminOrderDetail
{
    public function build(Order $order): array
    {
        $order->load(['user.customerProfile', 'items.mediaAsset', 'items.mediaCollection', 'items.mediaEntitlements',
            'paymentAttempts', 'refunds.items.orderItem', 'refunds.requestedBy', 'notifications']);
        $entitlementIds = $order->items->flatMap->mediaEntitlements->pluck('id');
        $downloads = OrderMediaAssetDownload::query()->whereIn('order_media_entitlement_id', $entitlementIds)
            ->with('asset')->latest('last_download_at')->get();

        return ['order' => $order, 'returnTo' => $this->returnTo(), 'sources' => $this->sources($order), 'downloads' => $downloads,
            'downloadSummary' => ['files' => $downloads->count(), 'requests' => (int) $downloads->sum('download_count'),
                'bytes' => (int) $downloads->sum('bytes_requested')],
            'quantities' => $order->items->groupBy(fn ($item) => (string) data_get($item->metadata, 'product', $item->item_type))
                ->map(fn (Collection $items, string $product) => ['product' => str($product)->replace('_', ' ')->title()->toString(),
                    'quantity' => $items->sum('quantity')])->values()];
    }

    private function returnTo(): string
    {
        $path = request()->query('return_to');
        if (is_string($path) && preg_match('~^/admin/(?:customers/[0-9]+|media/sales|orders)(?:\?[^\r\n#]*)?$~D', $path)) {
            return $path;
        }

        return route('admin.orders.index');
    }

    private function sources(Order $order): Collection
    {
        $groups = collect(data_get($order->metadata, 'cart_groups', []));
        if ($groups->isEmpty() && data_get($order->metadata, 'source_uuid')) {
            $groups = collect([['kind' => data_get($order->metadata, 'kind'),
                'source_uuid' => data_get($order->metadata, 'source_uuid')]]);
        }
        $competitions = SchedulingEvent::query()->whereIn('uuid', $groups->where('kind', 'competition')->pluck('source_uuid')->filter())->get()->keyBy('uuid');
        $studios = Studio::query()->whereIn('uuid', $groups->where('kind', 'concert')->pluck('source_uuid')->filter())->get()->keyBy('uuid');

        $concerts = Concert::with('bookingItem.schedulingEvent')->whereIn('uuid', $groups->where('kind', 'concert')->pluck('source_uuid'))->get()->keyBy('uuid');

        return $groups->map(function (array $group) use ($competitions, $studios, $concerts): array {
            $kind = (string) ($group['kind'] ?? 'media');
            $uuid = (string) ($group['source_uuid'] ?? '');
            $source = $kind === 'competition' ? $competitions->get($uuid) : ($concerts->get($uuid) ?? $studios->get($uuid));

            return ['gallery_url' => $kind === 'competition' && $source ? route('admin.competition-gallery.show', $source) : ($source instanceof Concert && $source->bookingItem?->schedulingEvent ? route('admin.concert-photo-gallery.show', $source->bookingItem->schedulingEvent) : null), 'kind' => $kind, 'uuid' => $uuid, 'name' => $source?->name ?? 'Unavailable or removed source',
                'subtotal' => $group['subtotal'] ?? null,
                'discount' => ((int) ($group['discount_code_amount'] ?? 0)) + ((int) ($group['bulk_discount_amount'] ?? 0)),
                'total' => $group['total'] ?? null];
        });
    }
}
