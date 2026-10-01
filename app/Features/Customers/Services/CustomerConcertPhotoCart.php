<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Media\Services\MediaPriceLists;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerConcertPhotoCart
{
    public function __construct(
        private readonly MediaCommerceSettings $settings,
        private readonly StudioPrincipalPhotoPricing $principalPricing,
        private readonly MediaPriceLists $priceLists,
        private readonly CustomerConcertGallery $gallery,
    ) {}

    public function save(User $user, Concert $concert, string $assetUuid, string $action): void
    {
        $asset = $this->gallery->assetForReleasedConcert($concert, $assetUuid);
        $assetConcert = $asset->collection->concert;
        if ($action === 'add' && $this->ownedAssetIds($user)->contains($asset->id)) {
            throw ValidationException::withMessages(['asset_uuid' => 'You already own this photo. Open it from My Media.']);
        }

        DB::transaction(function () use ($user, $concert, $asset, $assetConcert, $action): void {
            $selection = CustomerConcertPhotoSelection::firstOrCreate([
                'user_id' => $user->id, 'media_asset_id' => $asset->id,
            ], ['studio_id' => $concert->studio_id, 'concert_id' => $assetConcert->id]);
            $selection->update($action === 'favourite' ? ['is_favourite' => true]
                : ($action === 'unfavourite' ? ['is_favourite' => false]
                    : ($action === 'add' ? ['in_cart' => true] : ['in_cart' => false])));
            if (! $selection->is_favourite && ! $selection->in_cart) {
                $selection->delete();
            }
        });
    }

    public function state(User $user, Studio $studio, string $assetUuid): array
    {
        $selection = CustomerConcertPhotoSelection::query()->where('user_id', $user->id)
            ->whereHas('asset', fn ($query) => $query->where('uuid', $assetUuid))->first();
        $base = CustomerConcertPhotoSelection::query()->where('user_id', $user->id);

        return [
            'in_cart' => (bool) $selection?->in_cart,
            'is_favourite' => (bool) $selection?->is_favourite,
            'cart_count' => (clone $base)->where('in_cart', true)->count()
                + CustomerMediaSelection::query()->where('user_id', $user->id)->where('in_cart', true)->count(),
            'favourite_count' => (clone $base)->where('is_favourite', true)->count()
                + CustomerMediaSelection::query()->where('user_id', $user->id)->where('is_favourite', true)->count(),
        ];
    }

    public function forStudio(User $user, Studio $studio): array
    {
        abort_unless($user->canAccessCustomer() && app(FamilyGalleryAccess::class)->studios($user)
            ->where('studio_id', $studio->id)->whereNotNull('access_verified_at')->exists(), 403);
        $selections = CustomerConcertPhotoSelection::where('user_id', $user->id)->where('studio_id', $studio->id)
            ->with('asset.collection', 'concert')->get()->filter(function (CustomerConcertPhotoSelection $selection): bool {
                $asset = $selection->asset;
                $collection = $asset?->collection;

                return $selection->concert?->isPhotoGalleryAvailable() && $asset?->status->value === 'available'
                    && $asset->is_visible && $collection?->status->value === 'published'
                    && in_array($collection->visibility->value, ['customer', 'public'], true)
                    && $collection->published_at !== null;
            })->values();
        $cart = $selections->where('in_cart', true)->values();
        $principalPrice = $this->principalPricing->eligible($user, $studio);
        $photoPrice = $principalPrice ? StudioPrincipalPhotoPricing::PRICE
            : ($cart->first() ? $this->priceLists->concertPrice($cart->first()->concert, $cart->first()->asset?->collection)
                : $this->settings->price('concert_photo'));
        $lineAmounts = $cart->mapWithKeys(fn ($selection) => [$selection->id => $principalPrice
            ? StudioPrincipalPhotoPricing::PRICE
            : $this->priceLists->concertPrice($selection->concert, $selection->asset?->collection)])->all();
        $subtotal = array_sum($lineAmounts);
        $bulkEligibleSubtotal = $principalPrice ? 0 : $subtotal;
        $percent = $this->settings->discountPercent($bulkEligibleSubtotal);
        $discount = intdiv($bulkEligibleSubtotal * $percent, 100);

        $ownedAssetIds = $this->ownedAssetIds($user);
        $cartCount = CustomerConcertPhotoSelection::query()->where('user_id', $user->id)->where('in_cart', true)->count()
            + CustomerMediaSelection::query()->where('user_id', $user->id)->where('in_cart', true)->count();
        $favouriteCount = CustomerConcertPhotoSelection::query()->where('user_id', $user->id)->where('is_favourite', true)->count()
            + CustomerMediaSelection::query()->where('user_id', $user->id)->where('is_favourite', true)->count();

        return compact('studio', 'selections', 'cart', 'subtotal', 'percent', 'discount', 'ownedAssetIds',
            'photoPrice', 'principalPrice', 'lineAmounts', 'cartCount', 'favouriteCount')
            + ['bulk_eligible_subtotal' => $bulkEligibleSubtotal, 'total' => $subtotal - $discount];
    }

    private function ownedAssetIds(User $user)
    {
        return OrderItem::query()->whereNotNull('media_asset_id')->whereHas('order', fn ($query) => $query
            ->where('user_id', $user->id)->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value])
            ->whereNotNull('paid_at'))->pluck('media_asset_id');
    }
}
