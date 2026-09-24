<?php

namespace App\Features\Orders\Services;

use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;

class ReleasePendingCompetitionVideos
{
    public function __construct(private readonly ActivateOrderMediaEntitlement $activate) {}

    public function forCollection(SchedulingEvent $event, MediaCollection $collection): void
    {
        if ($collection->competition_id !== $event->id || $collection->media_type->value !== 'video'
            || $collection->status->value !== 'published' || $collection->visibility->value !== 'customer') {
            return;
        }
        $assets = $collection->assets()->where('status', 'available')->where('is_visible', true)->orderBy('sort_order')->get();
        if ($assets->isEmpty()) {
            return;
        }
        $itemUuid = $collection->metadata['item_uuid'] ?? null;
        $items = OrderItem::where('item_type', 'video')->where('metadata->item_uuid', $itemUuid)
            ->where('metadata->media_pending', true)
            ->whereHas('order', fn ($query) => $query->where('metadata->kind', 'competition')
                ->where('metadata->source_uuid', $event->uuid)->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value])
                ->whereNotNull('paid_at'))->get();
        foreach ($items as $item) {
            DB::transaction(function () use ($item, $collection, $assets): void {
                $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                if (! ($item->metadata['media_pending'] ?? false)) {
                    return;
                }
                $metadata = $item->metadata;
                $metadata['asset_uuids'] = $assets->pluck('uuid')->all();
                $metadata['media_pending'] = false;
                $item->update([
                    'media_collection_id' => $collection->id,
                    'snapshot_storage_disk' => $assets->first()->storage_disk,
                    'snapshot_storage_key' => $assets->first()->storage_key,
                    'snapshot_filename' => $assets->first()->original_filename,
                    'snapshot_display_name' => $collection->name,
                    'metadata' => $metadata,
                ]);
                $this->activate->execute($item->refresh(), 'video', $assets, notifyCustomer: true);
            });
        }
    }
}
