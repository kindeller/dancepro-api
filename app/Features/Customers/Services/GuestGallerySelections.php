<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Customers\Models\GuestGallerySelection;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Media\Services\MediaPriceLists;
use App\Features\Studios\Models\Studio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestGallerySelections
{
    public function __construct(
        private readonly MediaCommerceSettings $settings,
        private readonly MediaPriceLists $priceLists,
        private readonly CustomerConcertGallery $gallery,
    ) {}

    public function concert(GuestGalleryAccess $access, Concert $concert, array $input): array
    {
        $asset = $this->gallery->assetForReleasedConcert($concert, $input['asset_uuid'] ?? '');
        $this->save($access, [
            'concert_id' => $asset->collection->concert_id, 'media_asset_id' => $asset->id,
            'selection_key' => 'concert_photo:'.$asset->uuid, 'product' => 'photo',
        ], $input['action']);

        $selection = GuestGallerySelection::where('guest_gallery_access_id', $access->id)
            ->where('selection_key', 'concert_photo:'.$asset->uuid)->first();
        $base = GuestGallerySelection::query()->whereHas('access', fn ($query) => $query
            ->where('gallery_type', 'concert')->where('email_hash', $access->email_hash)
            ->whereHas('concert', fn ($concerts) => $concerts->where('studio_id', $concert->studio_id)));

        return ['asset_uuid' => $asset->uuid, 'in_cart' => (bool) $selection?->in_cart,
            'is_favourite' => (bool) $selection?->is_favourite,
            'cart_count' => (clone $base)->where('in_cart', true)->count(),
            'favourite_count' => (clone $base)->where('is_favourite', true)->count()];
    }

    public function forAccess(GuestGalleryAccess $access): Collection
    {
        return GuestGallerySelection::where('guest_gallery_access_id', $access->id)->get();
    }

    public function forStudio(GuestGalleryAccess $access, Studio $studio): array
    {
        abort_unless($access->gallery_type === 'concert' && $access->concert?->studio_id === $studio->id, 403);
        $selections = GuestGallerySelection::query()->whereHas('access', fn ($query) => $query
            ->where('gallery_type', 'concert')->where('email_hash', $access->email_hash)
            ->whereHas('concert', fn ($concerts) => $concerts->where('studio_id', $studio->id)))
            ->with('asset.collection.concert', 'access.concert')->get()->filter(function (GuestGallerySelection $selection): bool {
                $asset = $selection->asset;
                $collection = $asset?->collection;

                return $selection->access?->concert?->isPhotoGalleryAvailable() && $asset?->status->value === 'available'
                    && $asset->is_visible && $collection?->status->value === 'published'
                    && in_array($collection->visibility->value, ['customer', 'public'], true)
                    && $collection->published_at !== null;
            })->values();
        $selections->each(function (GuestGallerySelection $selection): void {
            $selection->setRelation('concert', $selection->asset->collection->concert);
        });
        $cart = $selections->where('in_cart', true)->values();
        $photoPrice = $cart->first()
            ? $this->priceLists->concertPrice($cart->first()->concert, $cart->first()->asset?->collection)
            : $this->settings->price('concert_photo');
        $lineAmounts = $cart->mapWithKeys(fn ($selection) => [$selection->id => $this->priceLists
            ->concertPrice($selection->concert, $selection->asset?->collection)])->all();
        $subtotal = array_sum($lineAmounts);
        $bulkEligibleSubtotal = $subtotal;
        $percent = $this->settings->discountPercent($bulkEligibleSubtotal);
        $discount = intdiv($bulkEligibleSubtotal * $percent, 100);

        return compact('studio', 'selections', 'cart', 'subtotal', 'percent', 'discount', 'photoPrice',
            'lineAmounts')
            + ['bulk_eligible_subtotal' => $bulkEligibleSubtotal, 'total' => $subtotal - $discount,
                'ownedAssetIds' => collect(), 'principalPrice' => false];
    }

    private function save(GuestGalleryAccess $access, array $attributes, string $action): void
    {
        DB::transaction(function () use ($access, $attributes, $action): void {
            if ($action === 'add') {
                $otherCart = GuestGallerySelection::where('in_cart', true)
                    ->whereHas('access', function ($query) use ($access): void {
                        $query->where('email_hash', $access->email_hash)->where('gallery_type', $access->gallery_type)
                            ->where('id', '!=', $access->id);
                    });
                if ($access->gallery_type === 'competition' && $otherCart->exists()) {
                    throw ValidationException::withMessages(['cart' => 'Finish or clear your current competition cart before starting another event.']);
                }
                if ($access->gallery_type === 'concert' && $otherCart->whereHas('access.concert', fn ($query) => $query
                    ->where('studio_id', '!=', $access->concert->studio_id))->exists()) {
                    throw ValidationException::withMessages(['cart' => 'Finish or clear your current studio cart before starting another studio.']);
                }
            }
            $selection = GuestGallerySelection::firstOrCreate([
                'guest_gallery_access_id' => $access->id, 'selection_key' => $attributes['selection_key'],
            ], $attributes);
            $selection->update($action === 'favourite' ? ['is_favourite' => true]
                : ($action === 'unfavourite' ? ['is_favourite' => false]
                    : ($action === 'add' ? ['in_cart' => true] : ['in_cart' => false])));
            if (! $selection->is_favourite && ! $selection->in_cart) {
                $selection->delete();
            }
        });
    }
}
