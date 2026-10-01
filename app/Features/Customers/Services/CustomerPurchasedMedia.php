<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Families\Services\FamilyAccess;
use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Models\OrderMediaAssetDownload;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionGallerySettings;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomerPurchasedMedia
{
    public function __construct(
        private readonly CustomerCompetitionAccess $competitionAccess,
        private readonly CompetitionGallerySettings $settings,
        private readonly CompetitionProgrammeNodes $programmeNodes,
    ) {}

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
                    'can_browse' => $kind === 'concert' || $this->competitionAccess->allows($user, $event)
                        || ($item->order && app(FamilyAccess::class)->canViewSharedOrder($user, $item->order)
                            && $item->mediaEntitlements->contains(fn ($entitlement) => $entitlement->ready_at && ! $entitlement->revoked_at)),
                    'thumbnail_url' => null, 'is_preview' => false];
                if ($result[$type][$key]['thumbnail_url'] === null) {
                    $photo = $this->assetsForItem($item)->first(fn (MediaAsset $asset) => $asset->media_type->value === 'photo'
                        && $asset->thumbnail_storage_disk && $asset->thumbnail_storage_key);
                    if ($photo) {
                        $result[$type][$key]['thumbnail_url'] = route('customer.media.library.thumbnail',
                            ['kind' => $kind, 'event' => $event->uuid, 'asset' => $photo->uuid, 'size' => 'cover']);
                    }
                }
            }
        }

        return ['photos' => array_values($result['photos']), 'videos' => array_values($result['videos'])];
    }

    public function albumForUser(User $user, string $kind, string $uuid, ?string $routineUuid = null): array
    {
        $event = $kind === 'competition'
            ? SchedulingEvent::where('uuid', $uuid)->where('event_type', 'competition')->whereNull('cancelled_at')->firstOrFail()
            : Concert::with('studio')->where('uuid', $uuid)->firstOrFail();
        $items = $this->paidItems($user)->filter(function (OrderItem $item) use ($kind, $event): bool {
            $collection = $item->mediaCollection ?? $item->mediaAsset?->collection;

            return $collection && ($kind === 'competition' ? $collection->competition_id : $collection->concert_id) === $event->id;
        });
        abort_if($items->isEmpty(), 404);

        $folders = $this->routineFolders($items, $kind, $event);
        $selectedFolder = $routineUuid ? $folders->firstWhere('uuid', $routineUuid) : null;
        abort_if($routineUuid && ! $selectedFolder, 404);
        $assets = $selectedFolder
            ? $selectedFolder['assets']
            : $folders->flatMap(fn (array $folder) => $folder['assets'])->unique('uuid')->values();
        $entitlements = $items->flatMap(fn (OrderItem $item) => $item->mediaEntitlements)
            ->filter(fn ($entitlement) => $entitlement->ready_at !== null && $entitlement->revoked_at === null);
        $entitlementByAsset = collect();
        foreach ($entitlements as $entitlement) {
            foreach ($entitlement->assets as $asset) {
                $entitlementByAsset->put($asset->id, $entitlement);
            }
        }
        $downloads = OrderMediaAssetDownload::query()->where('user_id', $user->id)
            ->whereIn('order_media_entitlement_id', $entitlements->pluck('id'))
            ->whereIn('media_asset_id', $assets->pluck('id'))->get()->keyBy('media_asset_id');
        $title = $kind === 'concert' ? $event->name : $this->settings->presentation($event)['title'];

        return ['event' => $event, 'kind' => $kind, 'title' => $title, 'folders' => $folders,
            'selectedFolder' => $selectedFolder, 'assets' => $assets,
            'entitlementByAsset' => $entitlementByAsset, 'downloads' => $downloads, 'is_preview' => false];
    }

    private function routineFolders(Collection $items, string $kind, SchedulingEvent|Concert $event): Collection
    {
        $competitionNodes = $kind === 'competition' ? $this->competitionNodes($event) : collect();

        return $items->groupBy(function (OrderItem $item): string {
            $collection = $item->mediaCollection ?? $item->mediaAsset?->collection;

            return (string) ($item->metadata['item_uuid'] ?? $collection?->metadata['item_uuid'] ?? $collection?->uuid);
        })->map(function (Collection $routineItems, string $routineUuid) use ($kind, $event, $competitionNodes): array {
            $collection = $routineItems->map(fn (OrderItem $item) => $item->mediaCollection ?? $item->mediaAsset?->collection)->filter()->first();
            $assets = $routineItems->flatMap(fn (OrderItem $item) => $this->assetsForItem($item))->unique('uuid')->values();
            $itemNode = $competitionNodes->get($routineUuid);
            $sectionNode = $itemNode ? $competitionNodes->get($itemNode['parent_uuid'] ?? '') : null;
            $cover = $assets->first(fn (MediaAsset $asset) => $asset->media_type->value === 'photo'
                && $asset->thumbnail_storage_disk && $asset->thumbnail_storage_key)
                ?? $assets->first(fn (MediaAsset $asset) => $asset->media_type->value === 'photo');

            return [
                'uuid' => $routineUuid,
                'title' => $itemNode['label'] ?? $itemNode['title'] ?? $collection?->name ?? 'Purchased routine',
                'context' => $kind === 'competition'
                    ? ($sectionNode['label'] ?? $sectionNode['title'] ?? $collection?->metadata['section_name'] ?? 'Competition routine')
                    : $event->name,
                'assets' => $assets,
                'cover' => $cover,
            ];
        })->sortBy(fn (array $folder) => strtolower($folder['context'].' '.$folder['title']))->values();
    }

    private function competitionNodes(SchedulingEvent $event): Collection
    {
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $plan = $gallery ? ProgrammeUploadPlan::find($gallery->programme_upload_plan_id) : null;

        return collect($plan ? $this->programmeNodes->nodes($plan) : [])->mapWithKeys(function (array $node): array {
            $node['label'] = trim(($node['folder_number'] ?? $node['number'] ?? '').' '.($node['folder_title'] ?? $node['title'] ?? ''));

            return isset($node['uuid']) ? [$node['uuid'] => $node] : [];
        });
    }

    private function paidItems(User $user): Collection
    {
        return OrderItem::query()->whereHas('order', fn ($query) => $query->whereIn('user_id', app(FamilyAccess::class)->purchaseOwnerIds($user))
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value])->whereNotNull('paid_at'))
            ->where(fn ($query) => $query->whereHas('order', fn ($order) => $order->where('user_id', $user->id))
                ->orWhereHas('mediaEntitlements', fn ($entitlements) => $entitlements->whereNotNull('ready_at')->whereNull('revoked_at')))
            ->with('order', 'mediaCollection.assets', 'mediaAsset.collection', 'mediaEntitlements.assets')->get();
    }

    private function assetsForItem(OrderItem $item): Collection
    {
        if ($item->mediaEntitlements->isNotEmpty()) {
            return $item->mediaEntitlements->filter(fn ($entitlement) => $entitlement->ready_at !== null && $entitlement->revoked_at === null)
                ->flatMap(fn ($entitlement) => $entitlement->assets)
                ->filter(fn (MediaAsset $asset) => $asset->is_visible && $asset->status->value === 'available')
                ->unique('uuid')->values();
        }
        $assets = $item->mediaAsset ? collect([$item->mediaAsset]) : ($item->mediaCollection?->assets ?? collect());

        return $assets->filter(fn (MediaAsset $asset) => $asset->is_visible && $asset->status->value === 'available')->values();
    }
}
