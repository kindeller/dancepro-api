<?php

namespace App\Features\Orders\Services;

use App\Features\Customers\Services\CompetitionOrderCatalogue;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Customers\Services\CustomerConcertPhotoCart;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Media\Services\MediaPriceLists;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Payments\Services\OrderDiscounts;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrepareCustomerOrder
{
    public function __construct(
        private readonly CustomerCompetitionAccess $competitionAccess,
        private readonly CustomerCompetitionCart $competitionCart,
        private readonly CustomerConcertPhotoCart $concertCart,
        private readonly CompetitionOrderCatalogue $competitionCatalogue,
        private readonly MediaCommerceSettings $settings,
        private readonly MediaPriceLists $priceLists,
        private readonly OrderDiscounts $discounts,
        private readonly CustomerCredits $credits,
    ) {}

    public function competition(User $user, SchedulingEvent $event, ?string $discountCode = null, bool $useAccountCredit = false): Order
    {
        abort_unless($this->competitionAccess->allows($user, $event), 403);

        return DB::transaction(function () use ($user, $event, $discountCode, $useAccountCredit): Order {
            $this->lockCustomer($user);
            $quote = $this->competitionCart->forEvent($user, $event);
            $cartSelections = collect($quote['cart'] ?? $quote['priced']);
            $selectionKeys = $cartSelections->pluck('selection_key')
                ->filter()->unique()->sort()->values()->all();
            $selectionIds = $cartSelections->pluck('id')->filter()->unique()->sort()->values()->all();
            $components = [];
            foreach ($quote['priced'] as $selection) {
                $price = $quote['lineAmounts'][$selection->id];
                $product = $selection->product;
                $metadata = ['product' => $product, 'item_uuid' => $selection->item_uuid,
                    'selection_key' => $selection->selection_key];
                $priceList = $this->priceLists->competition($event, $selection->item_uuid);
                $packagePrice = (int) $priceList->price('competition_routine_package');
                $videoPrice = (int) $priceList->price('competition_routine_video');
                $priceListProduct = 'competition_'.$product;
                $taxRate = $priceList->productIncludesGst($priceListProduct) ? $priceList->gst_rate_basis_points : 0;

                if (in_array($product, ['photo', 'routine_photos', 'routine_package'], true)) {
                    $routine = $this->competitionCatalogue->readyRoutine($event, $selection->item_uuid);
                    $assets = $routine['assets'];
                    if ($product === 'photo') {
                        $assets = $assets->where('metadata.programme_photo_file_uuid', $selection->photo_uuid)->values();
                        $this->requireAssets($assets);
                    }
                    $photoPrice = $product === 'routine_package' && $price === $packagePrice ? $price - $videoPrice : $price;
                    $components[] = $this->component('photo', $photoPrice, $routine['collection'], $assets, $metadata, $taxRate);
                }

                if (in_array($product, ['routine_video', 'routine_package'], true)
                    && ! ($product === 'routine_package' && $price !== $packagePrice)) {
                    [$collection, $assets] = $this->readyCompetitionVideo($event, $selection->item_uuid);
                    if ($product === 'routine_video') {
                        $this->requireAssets($assets);
                    }
                    $components[] = $this->component('video', $videoPrice, $collection, $assets, $metadata, $taxRate);
                }
            }

            $quote = $this->withDiscountCode($quote, $user, $discountCode);
            $quote = $this->withAccountCredit($quote, $user, $useAccountCredit);

            return $this->store($user, 'competition', $event->uuid, $quote, $components,
                ['cart_selection_keys' => $selectionKeys, 'cart_selection_ids' => $selectionIds]);
        });
    }

    public function concertPhotos(User $user, Studio $studio, ?string $discountCode = null, bool $useAccountCredit = false): Order
    {
        return DB::transaction(function () use ($user, $studio, $discountCode, $useAccountCredit): Order {
            $this->lockCustomer($user);
            $quote = $this->concertCart->forStudio($user, $studio);
            $assetUuids = $quote['cart']->map(fn ($selection) => $selection->asset?->uuid)
                ->filter()->unique()->sort()->values()->all();
            $selectionIds = $quote['cart']->pluck('id')->filter()->unique()->sort()->values()->all();
            $components = [];
            foreach ($quote['cart'] as $selection) {
                $asset = $selection->asset;
                $price = $quote['lineAmounts'][$selection->id];
                $priceList = $this->priceLists->concert($selection->concert, $asset->collection);
                $components[] = $this->component('photo', $price, $asset->collection, collect([$asset]),
                    ['product' => 'concert_photo', 'concert_uuid' => $selection->concert->uuid,
                        'principal_price' => $quote['principalPrice']],
                    $priceList->productIncludesGst('concert_photo') ? $priceList->gst_rate_basis_points : 0);
            }

            $quote = $this->withDiscountCode($quote, $user, $discountCode);
            $quote = $this->withAccountCredit($quote, $user, $useAccountCredit);

            return $this->store($user, 'concert', $studio->uuid, $quote, $components,
                ['cart_asset_uuids' => $assetUuids, 'cart_selection_ids' => $selectionIds]);
        });
    }

    private function lockCustomer(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
    }

    /** @return array{0: ?MediaCollection, 1: Collection<int, MediaAsset>} */
    private function readyCompetitionVideo(SchedulingEvent $event, string $itemUuid): array
    {
        if ($event->event_date?->copy()->addYear()->isPast()) {
            return [null, collect()];
        }
        $collection = MediaCollection::query()->where('competition_id', $event->id)
            ->where('media_type', 'video')->where('status', 'published')
            ->whereIn('visibility', ['customer', 'public'])->whereNotNull('published_at')
            ->where('metadata->item_uuid', $itemUuid)
            ->whereHas('assets', fn ($query) => $query->where('media_type', 'video')
                ->where('status', 'available')->where('is_visible', true))->first();
        $assets = $collection?->assets()->where('media_type', 'video')->where('status', 'available')
            ->where('is_visible', true)->orderBy('sort_order')->get() ?? collect();

        return [$assets->isEmpty() ? null : $collection, $assets];
    }

    private function requireAssets(Collection $assets): void
    {
        if ($assets->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Selected media is no longer available. Review your cart.']);
        }
    }

    private function component(string $type, int $price, ?MediaCollection $collection, Collection $assets, array $metadata, int $taxRate): array
    {
        $first = $assets->first();

        return [
            'media_collection_id' => $collection?->id,
            'media_asset_id' => $assets->count() === 1 ? $first?->id : null,
            'snapshot_storage_disk' => $first?->storage_disk,
            'snapshot_storage_key' => $first?->storage_key,
            'snapshot_filename' => $first?->original_filename,
            'snapshot_display_name' => $collection?->name ?? $first?->display_name ?? 'Pending routine video',
            'item_type' => $type,
            'quantity' => 1,
            'unit_price_amount' => $price,
            'tax_rate_basis_points' => $taxRate,
            'metadata' => $metadata + ['asset_uuids' => $assets->pluck('uuid')->all(), 'media_pending' => $assets->isEmpty()],
        ];
    }

    private function store(User $user, string $kind, string $sourceUuid, array $quote, array $components, array $selectionSnapshot): Order
    {
        if ($components === []) {
            throw ValidationException::withMessages(['cart' => 'Add media to your cart before reviewing an order.']);
        }
        $subtotal = array_sum(array_column($components, 'unit_price_amount'));
        if ($subtotal !== $quote['subtotal']) {
            throw ValidationException::withMessages(['cart' => 'Cart prices changed. Review your cart and try again.']);
        }

        $paymentTotal = $quote['payment_total'] ?? $quote['total'];
        $identity = [$kind, $sourceUuid, $quote['total'], $paymentTotal, $quote['code_discount'], $components, $selectionSnapshot];
        $fingerprint = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        $existing = Order::query()->where('user_id', $user->id)->where('status', OrderStatus::Draft)
            ->where('metadata->cart_fingerprint', $fingerprint)->first();
        if ($existing) {
            return $existing->load('items');
        }

        Order::query()->where('user_id', $user->id)->where('status', OrderStatus::Draft)
            ->where('metadata->kind', $kind)->where('metadata->source_uuid', $sourceUuid)
            ->update(['status' => OrderStatus::Cancelled->value, 'cancelled_at' => now()]);

        $order = Order::create([
            'user_id' => $user->id, 'customer_email' => $user->email, 'customer_name' => $user->name,
            'status' => OrderStatus::Draft, 'currency' => 'AUD', 'subtotal_amount' => $subtotal,
            'total_amount' => $paymentTotal, 'tax_amount' => 0,
            'metadata' => ['source' => 'customer_cart', 'kind' => $kind, 'source_uuid' => $sourceUuid,
                'cart_fingerprint' => $fingerprint, 'discount_amount' => $subtotal - $quote['total'],
                'bulk_discount_amount' => $quote['bulk_discount_amount'],
                'discount_code_id' => $quote['code_discount']['id'] ?? null,
                'discount_code' => $quote['code_discount']['code'] ?? null,
                'discount_code_type' => $quote['code_discount']['type'] ?? null,
                'discount_code_percent' => $quote['code_discount']['percent'] ?? null,
                'discount_code_configured_amount' => $quote['code_discount']['configured_amount'] ?? null,
                'discount_code_amount' => $quote['code_discount']['amount'] ?? 0,
                'total_before_account_credit' => $quote['total'],
                'account_credit_amount' => $quote['account_credit_amount'] ?? 0,
                'payment_amount' => $paymentTotal,
                'bulk_eligible_subtotal' => $quote['bulk_eligible_subtotal'] ?? $subtotal] + $selectionSnapshot,
        ]);
        $remaining = $quote['total'];
        $remainingAfterCredit = $paymentTotal;
        $last = count($components) - 1;
        $lines = [];
        foreach ($components as $index => $component) {
            $amount = $index === $last ? $remaining
                : intdiv($component['unit_price_amount'] * $quote['total'], $subtotal);
            $remaining -= $amount;
            $amountAfterCredit = $index === $last ? $remainingAfterCredit
                : ($quote['total'] === 0 ? 0 : intdiv($amount * $paymentTotal, $quote['total']));
            $remainingAfterCredit -= $amountAfterCredit;
            $component['metadata'] += [
                'account_credit_amount' => $amount - $amountAfterCredit,
                'net_after_account_credit' => $amountAfterCredit,
            ];
            $lines[] = $component + [
                'total_price_amount' => $amount,
                'tax_basis_amount' => $amountAfterCredit,
                'tax_amount' => 0,
            ];
        }
        foreach (collect($lines)->groupBy('tax_rate_basis_points', true) as $rate => $taxLines) {
            $rate = (int) $rate;
            if ($rate === 0) {
                continue;
            }
            $basis = $taxLines->sum('tax_basis_amount');
            $groupTax = (int) round($basis * $rate / (10000 + $rate));
            $remainingGroupTax = $groupTax;
            $lastTaxLine = $taxLines->keys()->last();
            foreach ($taxLines as $index => $line) {
                $lineTax = $index === $lastTaxLine ? $remainingGroupTax
                    : ($basis === 0 ? 0 : intdiv($line['tax_basis_amount'] * $groupTax, $basis));
                $remainingGroupTax -= $lineTax;
                $lines[$index]['tax_amount'] = $lineTax;
            }
        }
        $taxAmount = array_sum(array_column($lines, 'tax_amount'));
        foreach ($lines as $line) {
            unset($line['tax_basis_amount']);
            $order->items()->create($line);
        }
        $order->update(['tax_amount' => $taxAmount]);

        return $order->load('items');
    }

    private function withDiscountCode(array $quote, User $user, ?string $input): array
    {
        $quote['code_discount'] = $this->discounts->quote($input, $user, $quote['subtotal']);
        $codeAmount = $quote['code_discount']['amount'] ?? 0;
        $qualifying = max(0, ($quote['bulk_eligible_subtotal'] ?? $quote['subtotal']) - $codeAmount);
        $quote['discount_percent'] = $this->settings->discountPercent($qualifying);
        $quote['bulk_discount_amount'] = intdiv($qualifying * $quote['discount_percent'], 100);
        $quote['total'] = $quote['subtotal'] - $codeAmount - $quote['bulk_discount_amount'];

        return $quote;
    }

    private function withAccountCredit(array $quote, User $user, bool $useAccountCredit): array
    {
        $credit = $this->credits->availableForOrder($user, $quote['total'], $useAccountCredit);
        $quote['account_credit_amount'] = $credit;
        $quote['payment_total'] = $quote['total'] - $credit;

        return $quote;
    }
}
