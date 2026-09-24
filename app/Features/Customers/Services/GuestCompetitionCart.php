<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Customers\Models\GuestGallerySelection;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Media\Services\MediaPriceLists;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GuestCompetitionCart
{
    public function __construct(
        private readonly CompetitionCustomerPreview $gallery,
        private readonly MediaCommerceSettings $settings,
        private readonly MediaPriceLists $priceLists,
    ) {}

    public function save(GuestGalleryAccess $access, SchedulingEvent $event, array $input): array
    {
        $product = $input['product'] ?? 'photo';
        $item = $input['item_uuid'] ?? null;
        $photo = $input['photo_uuid'] ?? null;
        $action = $input['action'];
        if (! $item || ($product === 'photo' && ! $photo) || ($product !== 'photo' && $photo)) {
            throw ValidationException::withMessages(['photo_uuid' => 'Choose a photo for an individual photo selection.']);
        }

        $routine = $this->gallery->browse($event, $item, 'guest-galleries.competitions.image');
        abort_unless(($routine['selected']['kind'] ?? null) === 'item', 404);
        if ($action === 'add' && in_array($product, ['photo', 'routine_photos', 'routine_package'], true)
            && ! ($routine['photos']?->total() ?? 0)) {
            throw ValidationException::withMessages(['product' => 'Photos are not available for this routine yet.']);
        }
        if ($photo) {
            $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->firstOrFail();
            abort_unless(ProgrammePhotoFile::inGallery($gallery)->where('contributions.item_uuid', $item)
                ->where('programme_photo_files.uuid', $photo)->exists(), 404);
        }
        if ($product === 'routine_video' && $action === 'add' && ! $this->videoAvailable($event, $item)) {
            throw ValidationException::withMessages(['product' => 'This routine’s video is not available for purchase yet.']);
        }

        $selection = DB::transaction(function () use ($access, $event, $product, $item, $photo, $action): GuestGallerySelection {
            if ($action === 'add' && GuestGallerySelection::where('in_cart', true)
                ->whereHas('access', fn ($query) => $query->where('email_hash', $access->email_hash)
                    ->where('gallery_type', 'competition')->where('id', '!=', $access->id))->exists()) {
                throw ValidationException::withMessages(['cart' => 'Finish or clear your current competition cart before starting another event.']);
            }
            $selection = GuestGallerySelection::firstOrCreate([
                'guest_gallery_access_id' => $access->id, 'selection_key' => $product.':'.($photo ?? $item),
            ], ['scheduling_event_id' => $event->id, 'product' => $product, 'item_uuid' => $item, 'photo_uuid' => $photo]);
            $selection->update($action === 'favourite' ? ['is_favourite' => true]
                : ($action === 'unfavourite' ? ['is_favourite' => false]
                    : ($action === 'add' ? ['in_cart' => true] : ['in_cart' => false])));
            if (! $selection->is_favourite && ! $selection->in_cart) {
                $selection->delete();
            }

            return $selection;
        });

        return ['in_cart' => (bool) $selection->in_cart, 'is_favourite' => (bool) $selection->is_favourite]
            + $this->counts($access);
    }

    public function savePhotos(GuestGalleryAccess $access, SchedulingEvent $event, string $itemUuid, array $photoUuids, string $action): void
    {
        $routine = $this->gallery->browse($event, $itemUuid, 'guest-galleries.competitions.image');
        abort_unless(($routine['selected']['kind'] ?? null) === 'item', 404);
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->firstOrFail();
        abort_unless(ProgrammePhotoFile::inGallery($gallery)->where('contributions.item_uuid', $itemUuid)
            ->whereIn('programme_photo_files.uuid', $photoUuids)->count() === count($photoUuids), 404);

        DB::transaction(function () use ($access, $event, $itemUuid, $photoUuids, $action): void {
            if ($action === 'add' && GuestGallerySelection::where('in_cart', true)
                ->whereHas('access', fn ($query) => $query->where('email_hash', $access->email_hash)
                    ->where('gallery_type', 'competition')->where('id', '!=', $access->id))->exists()) {
                throw ValidationException::withMessages(['cart' => 'Finish or clear your current competition cart before starting another event.']);
            }
            foreach ($photoUuids as $photoUuid) {
                $selection = GuestGallerySelection::firstOrCreate([
                    'guest_gallery_access_id' => $access->id, 'selection_key' => 'photo:'.$photoUuid,
                ], ['scheduling_event_id' => $event->id, 'product' => 'photo', 'item_uuid' => $itemUuid, 'photo_uuid' => $photoUuid]);
                $selection->update($action === 'add' ? ['in_cart' => true] : ['is_favourite' => true]);
            }
        });
    }

    public function forEvent(GuestGalleryAccess $access, SchedulingEvent $event, bool $includeCards = false): array
    {
        $selections = GuestGallerySelection::where('guest_gallery_access_id', $access->id)->get();
        $routineDetails = ($includeCards ? $selections->pluck('item_uuid')->filter()->unique() : collect())
            ->mapWithKeys(function (string $uuid) use ($event): array {
                try {
                    $data = $this->gallery->browse($event, $uuid, 'guest-galleries.competitions.image');

                    return [$uuid => [
                        'competitor' => $data['selected']['label'] ?? 'Routine',
                        'section' => collect($data['breadcrumbs'])->firstWhere('kind', 'section')['label'] ?? 'Section unavailable',
                        'thumbnail_photo_uuid' => $data['photos']?->first()?->uuid, 'available' => true,
                    ]];
                } catch (HttpException) {
                    return [$uuid => ['competitor' => 'Unavailable routine', 'section' => 'Section unavailable',
                        'thumbnail_photo_uuid' => null, 'available' => false]];
                }
            })->all();
        $gallery = $includeCards ? ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first() : null;
        $selectionDetails = ($includeCards ? $selections : collect())->mapWithKeys(function (GuestGallerySelection $selection) use ($event, $gallery, $routineDetails): array {
            $routine = $routineDetails[$selection->item_uuid];
            $photoUuid = $selection->photo_uuid;
            $page = 1;
            if ($photoUuid && $gallery && $routine['available']) {
                $photo = ProgrammePhotoFile::inGallery($gallery)->where('programme_photo_files.uuid', $photoUuid)
                    ->where('contributions.item_uuid', $selection->item_uuid)->first();
                if ($photo) {
                    $position = ProgrammePhotoFile::inGallery($gallery)->where('contributions.item_uuid', $selection->item_uuid)
                        ->where('programme_photo_files.id', '<=', $photo->id)->count();
                    $page = max(1, (int) ceil($position / 48));
                } else {
                    $photoUuid = null;
                }
            } elseif ($photoUuid) {
                $photoUuid = null;
            }
            $thumbnailUuid = $photoUuid ?? $routine['thumbnail_photo_uuid'];

            return [$selection->id => $routine + [
                'thumbnail_url' => $thumbnailUuid ? route('guest-galleries.competitions.image', [
                    'schedulingEvent' => $event, 'photo' => $thumbnailUuid, 'size' => 'thumbnail',
                ]) : $event->crewLogoUrl(),
                'view_url' => $routine['available'] ? route('guest-galleries.competitions.show', array_filter([
                    'schedulingEvent' => $event, 'folder' => $selection->item_uuid,
                    'page' => $photoUuid ? $page : null, 'photo' => $photoUuid,
                ], fn ($value) => $value !== null)) : route('guest-galleries.competitions.show', $event),
                'can_add' => match ($selection->product) {
                    'photo' => $photoUuid !== null,
                    'routine_video' => $routine['available'] && $this->videoAvailable($event, $selection->item_uuid),
                    default => $routine['thumbnail_photo_uuid'] !== null,
                },
            ]];
        })->all();
        $cart = $selections->where('in_cart', true)->values();
        $priced = collect();
        $notices = [];
        foreach ($cart->groupBy('item_uuid') as $items) {
            $itemUuid = (string) $items->first()->item_uuid;
            $photoPrice = $this->priceLists->competitionPrice($event, 'competition_photo', $itemUuid);
            $routinePhotosPrice = $this->priceLists->competitionPrice($event, 'competition_routine_photos', $itemUuid);
            $videoPrice = $this->priceLists->competitionPrice($event, 'competition_routine_video', $itemUuid);
            $packagePrice = $this->priceLists->competitionPrice($event, 'competition_routine_package', $itemUuid);
            $package = $items->firstWhere('product', 'routine_package');
            $photos = $items->firstWhere('product', 'routine_photos');
            $video = $items->firstWhere('product', 'routine_video');
            if ($package) {
                $priced->push($package);
            } else {
                $priced->push(...($photos ? [$photos] : $items->where('product', 'photo')->values()->all()));
                if ($video) {
                    $priced->push($video);
                }
            }
            $photoCount = $items->where('product', 'photo')->count();
            if ($this->settings->get('upsell_enabled') && ! $package && ! $photos
                && $photoCount * $photoPrice > $routinePhotosPrice) {
                $notices[] = 'All photos from one routine cost $'.number_format($routinePhotosPrice / 100, 2).'; your selected photos from that routine cost $'.number_format($photoCount * $photoPrice / 100, 2).'.';
            }
            if ($this->settings->get('upsell_enabled') && ! $package && $photos && $video) {
                $saving = $routinePhotosPrice + $videoPrice - $packagePrice;
                if ($saving > 0) {
                    $notices[] = 'The routine package costs $'.number_format($packagePrice / 100, 2).', saving $'.number_format($saving / 100, 2).' over separate photo and video albums.';
                }
            }
        }
        $lineAmounts = $priced->mapWithKeys(fn ($selection) => [$selection->id => $this->priceLists
            ->competitionPrice($event, 'competition_'.$selection->product, $selection->item_uuid)])->all();
        $subtotal = array_sum($lineAmounts);
        $percent = $this->settings->discountPercent($subtotal);
        $threshold = $this->settings->nextDiscountThreshold($subtotal);
        if ($this->settings->get('upsell_enabled') && $threshold && $threshold - $subtotal <= 2000) {
            $notices[] = 'Add $'.number_format(($threshold - $subtotal) / 100, 2).' to reach the next discount level.';
        }

        return ['selections' => $selections, 'selectionDetails' => $selectionDetails, 'cart' => $cart, 'priced' => $priced,
            'owned' => ['keys' => [], 'routine_photos' => [], 'routine_videos' => [], 'packages' => []],
            'lineAmounts' => $lineAmounts, 'subtotal' => $subtotal, 'bulk_eligible_subtotal' => $subtotal, 'discount_percent' => $percent,
            'discount' => intdiv($subtotal * $percent, 100), 'total' => $subtotal - intdiv($subtotal * $percent, 100),
            'notices' => $notices];
    }

    public function videoAvailable(SchedulingEvent $event, string $item): bool
    {
        if ($event->event_date?->copy()->addYear()->isPast()) {
            return false;
        }

        return MediaCollection::where('competition_id', $event->id)->where('status', 'published')
            ->whereIn('visibility', ['customer', 'public'])->whereNotNull('published_at')
            ->where('media_type', 'video')->where('metadata->item_uuid', $item)
            ->whereHas('assets', fn ($query) => $query->where('media_type', 'video')
                ->where('status', 'available')->where('is_visible', true))->exists();
    }

    public function counts(GuestGalleryAccess $access): array
    {
        $query = GuestGallerySelection::where('guest_gallery_access_id', $access->id);

        return ['cart_count' => (clone $query)->where('in_cart', true)->count(),
            'favourite_count' => (clone $query)->where('is_favourite', true)->count()];
    }
}
