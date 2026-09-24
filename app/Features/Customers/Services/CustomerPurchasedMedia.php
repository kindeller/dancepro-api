<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Programmes\Services\CompetitionGallerySettings;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomerPurchasedMedia
{
    public function __construct(private readonly CustomerCompetitionAccess $competitionAccess, private readonly CompetitionGallerySettings $settings) {}

    public function forUser(User $user): array
    {
        $items = $this->paidItems($user);
        $collections = $items->map(fn ($item) => $item->mediaCollection ?? $item->mediaAsset?->collection)->filter();
        $concerts = Concert::with('studio')->whereIn('id', $collections->pluck('concert_id')->filter()->unique())->get()->keyBy('id');
        $events = SchedulingEvent::with('competitionContact')->whereIn('id', $collections->pluck('competition_id')->filter()->unique())
            ->where('event_type', 'competition')->whereNull('cancelled_at')->get()->keyBy('id');
        $titles = $this->settings->forEvents($events->values());
        $result = ['photos' => [], 'videos' => []];

        foreach ($items as $item) {
            $collection = $item->mediaCollection ?? $item->mediaAsset?->collection;
            if (! $collection) {
                continue;
            }
            $event = $collection->concert_id ? $concerts->get($collection->concert_id) : $events->get($collection->competition_id);
            if (! $event) {
                continue;
            }
            $kind = $collection->concert_id ? 'concert' : 'competition';
            $types = match ($collection->media_type->value) {
                'photo' => ['photos'], 'video' => ['videos'], 'mixed' => ['photos', 'videos'], default => [],
            };
            foreach ($types as $type) {
                $key = $kind.':'.$event->id;
                $result[$type][$key] ??= ['event' => $event, 'kind' => $kind,
                    'title' => $kind === 'concert' ? $event->name : $titles[$event->uuid]['title'],
                    'can_browse' => $kind === 'concert' || $this->competitionAccess->allows($user, $event),
                    'thumbnail_url' => null, 'is_preview' => false];
                if ($result[$type][$key]['thumbnail_url'] === null) {
                    $photo = $this->assetsForItem($item)->first(fn (MediaAsset $asset) => $asset->media_type->value === 'photo'
                        && $asset->thumbnail_storage_disk && $asset->thumbnail_storage_key);
                    if ($photo) {
                        $result[$type][$key]['thumbnail_url'] = route('customer.media.library.thumbnail',
                            ['kind' => $kind, 'event' => $event->uuid, 'asset' => $photo->uuid]);
                    }
                }
            }
        }

        return ['photos' => array_values($result['photos']), 'videos' => array_values($result['videos'])];
    }

    public function albumForUser(User $user, string $kind, string $uuid): array
    {
        $event = $kind === 'competition'
            ? SchedulingEvent::where('uuid', $uuid)->where('event_type', 'competition')->whereNull('cancelled_at')->firstOrFail()
            : Concert::with('studio')->where('uuid', $uuid)->firstOrFail();
        $items = $this->paidItems($user)->filter(function (OrderItem $item) use ($kind, $event): bool {
            $collection = $item->mediaCollection ?? $item->mediaAsset?->collection;

            return $collection && ($kind === 'competition' ? $collection->competition_id : $collection->concert_id) === $event->id;
        });
        abort_if($items->isEmpty(), 404);

        $assets = $items->flatMap(fn (OrderItem $item) => $this->assetsForItem($item))->unique('uuid')->values();
        $title = $kind === 'concert' ? $event->name : $this->settings->presentation($event)['title'];

        return ['event' => $event, 'kind' => $kind, 'title' => $title, 'assets' => $assets, 'is_preview' => false];
    }

    private function paidItems(User $user): Collection
    {
        return OrderItem::query()->whereHas('order', fn ($query) => $query->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value])->whereNotNull('paid_at'))
            ->with('mediaCollection.assets', 'mediaAsset.collection', 'mediaEntitlements.assets')->get();
    }

    private function assetsForItem(OrderItem $item): Collection
    {
        if ($item->mediaEntitlements->isNotEmpty()) {
            return $item->mediaEntitlements->filter(fn ($entitlement) => $entitlement->ready_at !== null)
                ->flatMap(fn ($entitlement) => $entitlement->assets)
                ->filter(fn (MediaAsset $asset) => $asset->is_visible && $asset->status->value === 'available')
                ->unique('uuid')->values();
        }
        $assets = $item->mediaAsset ? collect([$item->mediaAsset]) : ($item->mediaCollection?->assets ?? collect());

        return $assets->filter(fn (MediaAsset $asset) => $asset->is_visible && $asset->status->value === 'available')->values();
    }
}
