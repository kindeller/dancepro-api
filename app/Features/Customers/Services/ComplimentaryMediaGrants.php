<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Concerts\Support\ConcertAccessGrantSource;
use App\Features\Concerts\Support\ConcertAccessGrantStatus;
use App\Features\Customers\Models\CustomerAccountActivity;
use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Notifications\CustomerAccountMessage;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Services\ActivateOrderMediaEntitlement;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplimentaryMediaGrants
{
    public function __construct(
        private readonly CompetitionOrderCatalogue $competitionCatalogue,
        private readonly ActivateOrderMediaEntitlement $activate,
    ) {}

    public function grant(User $administrator, array $input): Order
    {
        $customer = $this->customer($input['customer_lookup']);
        $components = $this->withoutExistingAccess($customer, $this->components($input));
        if ($components->isEmpty()) {
            throw ValidationException::withMessages(['source_uuid' => 'This account already has access to all of that media.']);
        }

        $order = DB::transaction(function () use ($administrator, $customer, $components, $input): Order {
            $order = Order::create([
                'user_id' => $customer->id,
                'customer_email' => $customer->email,
                'customer_name' => $customer->name,
                'status' => OrderStatus::Fulfilled,
                'currency' => 'AUD',
                'subtotal_amount' => 0,
                'total_amount' => 0,
                'tax_amount' => 0,
                'placed_at' => now(),
                'paid_at' => now(),
                'fulfilled_at' => now(),
                'metadata' => [
                    'source' => 'complimentary_media_grant',
                    'kind' => str_starts_with($input['source_kind'], 'competition') ? 'competition' : 'concert',
                    'source_uuid' => $input['event_uuid'] ?? null,
                    'source_kind' => $input['source_kind'],
                    'granted_by_user_id' => $administrator->id,
                    'grant_note' => $input['note'] ?? null,
                ],
            ]);

            foreach ($components as $component) {
                /** @var MediaCollection $collection */
                $collection = $component['collection'];
                /** @var Collection<int, MediaAsset> $assets */
                $assets = $component['assets'];
                $first = $assets->first();
                $item = $order->items()->create([
                    'media_collection_id' => $collection->id,
                    'media_asset_id' => $assets->count() === 1 ? $first->id : null,
                    'snapshot_storage_disk' => $first->storage_disk,
                    'snapshot_storage_key' => $first->storage_key,
                    'snapshot_filename' => $first->original_filename,
                    'snapshot_display_name' => $collection->name,
                    'item_type' => 'complimentary_'.$component['type'],
                    'quantity' => 1,
                    'unit_price_amount' => 0,
                    'total_price_amount' => 0,
                    'tax_rate_basis_points' => 0,
                    'tax_amount' => 0,
                    'metadata' => [
                        'product' => 'complimentary_'.$component['type'],
                        'item_uuid' => $collection->metadata['item_uuid'] ?? $collection->uuid,
                        'asset_uuids' => $assets->pluck('uuid')->sort()->values()->all(),
                        'complimentary' => true,
                        'granted_by_user_id' => $administrator->id,
                    ],
                ]);
                $this->activate->execute($item, $component['type'], $assets);
            }

            $this->grantEventAccess($customer, $administrator, $components, $input, $order);

            return $order->load('items.mediaEntitlements.assets');
        });

        $this->activity($customer, $administrator, 'complimentary_media_granted', 'Complimentary media order '.$order->displayNumber().' added.', $order);
        if ($input['notify_customer'] ?? false) {
            $this->notify($customer, $administrator, 'Complimentary media added to your DancePro account', 'We have added media to your DancePro account at no cost. Sign in to My Media to view and download it.', $order);
        }

        return $order;
    }

    public function revoke(Order $order, User $administrator, string $reason, bool $notifyCustomer): void
    {
        abort_unless(data_get($order->metadata, 'source') === 'complimentary_media_grant' && $order->user_id, 404);
        $customer = $order->user()->firstOrFail();

        DB::transaction(function () use ($order, $administrator, $reason): void {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->with('items.mediaEntitlements')->firstOrFail();
            $entitlements = $order->items->flatMap->mediaEntitlements->whereNull('revoked_at');
            if ($entitlements->isEmpty()) {
                throw ValidationException::withMessages(['reason' => 'This complimentary access has already been revoked.']);
            }
            foreach ($entitlements as $entitlement) {
                $entitlement->update([
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $administrator->id,
                    'revoke_reason' => $reason,
                    'expires_at' => now(),
                ]);
            }
            $order->update(['metadata' => array_merge($order->metadata ?? [], [
                'complimentary_access_revoked_at' => now()->toIso8601String(),
                'complimentary_access_revoked_by_user_id' => $administrator->id,
                'complimentary_access_revoke_reason' => $reason,
            ])]);
        });

        $this->activity($customer, $administrator, 'complimentary_media_revoked', 'Complimentary media order '.$order->displayNumber().' revoked.', $order, ['reason' => $reason]);
        if ($notifyCustomer) {
            $this->notify($customer, $administrator, 'DancePro media access update', 'Complimentary media previously added to your account has been removed. Contact DancePro through Help if you believe this was a mistake.', $order);
        }
    }

    private function customer(string $lookup): User
    {
        $lookup = trim($lookup);
        $customer = User::query()->where('is_active', true)->where('type', 'customer')
            ->where(function ($query) use ($lookup): void {
                if (ctype_digit($lookup)) {
                    $query->whereKey((int) $lookup);
                } else {
                    $query->whereRaw('lower(email) = ?', [mb_strtolower($lookup)]);
                }
            })->first();

        if (! $customer) {
            throw ValidationException::withMessages(['customer_lookup' => 'No active customer account matches that email address or account ID.']);
        }

        return $customer;
    }

    private function components(array $input): Collection
    {
        if (str_starts_with($input['source_kind'], 'competition')) {
            $event = SchedulingEvent::where('uuid', $input['event_uuid'] ?? null)->where('event_type', 'competition')->firstOrFail();
            if ($input['source_kind'] === 'competition_video') {
                $video = $this->competitionVideo($event, $input['source_uuid']);

                return collect([['type' => 'video', 'collection' => $video, 'assets' => $video->assets]]);
            }
            $itemUuid = $input['source_kind'] === 'competition_item'
                ? $input['source_uuid']
                : $this->competitionItemUuid($event, $input['source_uuid']);
            $routine = $this->competitionCatalogue->readyRoutine($event, $itemUuid);
            $photos = $input['source_kind'] === 'competition_photo'
                ? $routine['assets']->where('metadata.programme_photo_file_uuid', $input['source_uuid'])->values()
                : $routine['assets'];
            $components = collect([['type' => 'photo', 'collection' => $routine['collection'], 'assets' => $photos]]);
            if (($input['include_video'] ?? false) && $input['source_kind'] === 'competition_item') {
                $video = $this->competitionVideo($event, $itemUuid);
                $components->push(['type' => 'video', 'collection' => $video, 'assets' => $video->assets]);
            }

            return $components;
        }

        if ($input['source_kind'] === 'concert_photo') {
            $asset = MediaAsset::where('uuid', $input['source_uuid'])->where('media_type', 'photo')
                ->where('status', 'available')->where('is_visible', true)->with('collection')->firstOrFail();

            return collect([['type' => 'photo', 'collection' => $asset->collection, 'assets' => collect([$asset])]]);
        }

        $collection = MediaCollection::where('uuid', $input['source_uuid'])->where('media_type', 'photo')
            ->with(['assets' => fn ($query) => $query->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)->orderBy('sort_order')])->firstOrFail();

        return collect([['type' => 'photo', 'collection' => $collection, 'assets' => $collection->assets]]);
    }

    private function withoutExistingAccess(User $customer, Collection $components): Collection
    {
        $assetIds = $components->flatMap(fn (array $component) => $component['assets']->pluck('id'))->unique();
        $owned = DB::table('order_media_entitlement_assets as entitlement_assets')
            ->join('order_media_entitlements as entitlements', 'entitlements.id', '=', 'entitlement_assets.order_media_entitlement_id')
            ->join('order_items', 'order_items.id', '=', 'entitlements.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $customer->id)->whereIn('orders.status', ['paid', 'fulfilled'])
            ->whereNull('entitlements.revoked_at')
            ->whereIn('entitlement_assets.media_asset_id', $assetIds)->pluck('entitlement_assets.media_asset_id');

        return $components->map(function (array $component) use ($owned): array {
            $component['assets'] = $component['assets']->whereNotIn('id', $owned)->values();

            return $component;
        })->filter(fn (array $component): bool => $component['assets']->isNotEmpty())->values();
    }

    private function grantEventAccess(User $customer, User $administrator, Collection $components, array $input, Order $order): void
    {
        if (str_starts_with($input['source_kind'], 'competition')) {
            $event = SchedulingEvent::where('uuid', $input['event_uuid'] ?? null)
                ->where('event_type', 'competition')->firstOrFail();
            CustomerCompetitionRegistration::updateOrCreate(
                ['user_id' => $customer->id, 'scheduling_event_id' => $event->id],
                ['access_verified_at' => now()],
            );

            return;
        }

        $concertIds = $components->pluck('collection.concert_id')->filter()->unique();
        foreach ($concertIds as $concertId) {
            ConcertAccessGrant::updateOrCreate(
                ['concert_id' => $concertId, 'user_id' => $customer->id, 'source' => ConcertAccessGrantSource::Order->value],
                [
                    'email' => $customer->email,
                    'status' => ConcertAccessGrantStatus::Claimed->value,
                    'granted_by_user_id' => $administrator->id,
                    'claimed_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                    'revoke_reason' => null,
                    'metadata' => ['order_uuid' => $order->uuid, 'complimentary' => true],
                ],
            );
        }
    }

    private function competitionVideo(SchedulingEvent $event, string $itemUuid): MediaCollection
    {
        $video = MediaCollection::query()->where('competition_id', $event->id)->where('media_type', 'video')
            ->where('metadata->item_uuid', $itemUuid)->where('status', 'published')
            ->whereHas('assets', fn ($query) => $query->where('media_type', 'video')->where('status', 'available')->where('is_visible', true))
            ->with(['assets' => fn ($query) => $query->where('media_type', 'video')->where('status', 'available')->where('is_visible', true)->orderBy('sort_order')])->first();
        if (! $video) {
            throw ValidationException::withMessages(['include_video' => 'There is no released video available for this competition item.']);
        }

        return $video;
    }

    private function competitionItemUuid(SchedulingEvent $event, string $photoUuid): string
    {
        $asset = MediaAsset::query()->where('metadata->programme_photo_file_uuid', $photoUuid)
            ->whereHas('collection', fn ($query) => $query->where('competition_id', $event->id))->with('collection')->first();
        if ($asset?->collection?->metadata['item_uuid'] ?? null) {
            return $asset->collection->metadata['item_uuid'];
        }

        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->firstOrFail();
        $file = ProgrammePhotoFile::inGallery($gallery)
            ->where('programme_photo_files.uuid', $photoUuid)->firstOrFail();
        $contribution = $file->contribution()->firstOrFail();

        return (string) $contribution->item_uuid;
    }

    private function notify(User $customer, User $administrator, string $subject, string $body, Order $order): void
    {
        $email = CustomerAccountEmail::create([
            'user_id' => $customer->id,
            'sent_by_user_id' => $administrator->id,
            'recipient_email' => $customer->email,
            'subject' => $subject,
            'body' => $body,
            'status' => 'queued',
            'queued_at' => now(),
            'metadata' => ['order_uuid' => $order->uuid, 'kind' => 'complimentary_media'],
        ]);
        try {
            $customer->notify(new CustomerAccountMessage($subject, $body));
            $email->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            report($exception);
            $email->update(['status' => 'failed', 'failed_at' => now(), 'metadata' => array_merge($email->metadata ?? [], ['error' => $exception->getMessage()])]);
        }
    }

    private function activity(User $customer, User $administrator, string $type, string $summary, Order $order, array $metadata = []): void
    {
        CustomerAccountActivity::create([
            'user_id' => $customer->id,
            'actor_user_id' => $administrator->id,
            'type' => $type,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'summary' => $summary,
            'metadata' => array_merge($metadata, ['order_uuid' => $order->uuid]),
        ]);
    }
}
