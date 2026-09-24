<?php

namespace App\Features\Media\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaPriceList;
use App\Features\Programmes\Models\ProgrammeNode;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MediaPriceLists
{
    public const PRODUCTS = [
        'competition' => [
            'competition_photo' => 'Individual photo',
            'competition_routine_photos' => 'All photos from one routine',
            'competition_routine_video' => 'Routine video',
            'competition_routine_package' => 'Photos + video package',
        ],
        'concert' => [
            'concert_photo' => 'Individual photo',
        ],
    ];

    public function __construct(private readonly MediaCommerceSettings $legacySettings) {}

    /** @return Collection<int, MediaPriceList> */
    public function all(): Collection
    {
        $this->ensureDefaults();

        $lists = MediaPriceList::query()->withCount([
            'competitionEvents as direct_competition_galleries_count',
            'concerts as direct_concert_galleries_count',
            'collections as assigned_albums_count',
            'programmeNodes as assigned_routines_count',
        ])->orderByDesc('is_default')->orderBy('gallery_type')->orderBy('name')->get();
        $automaticCompetitionGalleries = SchedulingEvent::query()->where('event_type', 'competition')
            ->whereNull('media_price_list_id')->count();
        $automaticConcertGalleries = Concert::query()->whereNull('media_price_list_id')->count();

        return $lists->each(function (MediaPriceList $list) use ($automaticCompetitionGalleries, $automaticConcertGalleries): void {
            $direct = $list->gallery_type === 'competition'
                ? $list->direct_competition_galleries_count
                : $list->direct_concert_galleries_count;
            $automatic = $list->is_default
                ? ($list->gallery_type === 'competition' ? $automaticCompetitionGalleries : $automaticConcertGalleries)
                : 0;
            $list->setAttribute('assigned_galleries_count', $direct + $automatic);
            $list->setAttribute('assigned_overrides_count', $list->assigned_albums_count + $list->assigned_routines_count);
        });
    }

    public function ensureDefaults(): void
    {
        foreach (['competition' => 'Competition default', 'concert' => 'Concert default'] as $type => $name) {
            if (MediaPriceList::query()->where('gallery_type', $type)->where('is_default', true)->exists()) {
                continue;
            }
            DB::transaction(function () use ($type, $name): void {
                if (MediaPriceList::query()->where('gallery_type', $type)->where('is_default', true)->lockForUpdate()->exists()) {
                    return;
                }
                MediaPriceList::query()->create([
                    'name' => $name,
                    'gallery_type' => $type,
                    'prices' => collect(self::PRODUCTS[$type])->mapWithKeys(
                        fn (string $label, string $key): array => [$key => $this->legacySettings->price($key)],
                    )->all(),
                    'gst_rate_basis_points' => 1000,
                    'taxable_products' => array_keys(self::PRODUCTS[$type]),
                    'is_default' => true,
                    'is_active' => true,
                ]);
            });
        }
    }

    public function save(?MediaPriceList $priceList, User $actor, array $input): MediaPriceList
    {
        $type = $input['gallery_type'];
        $prices = collect(self::PRODUCTS[$type])->mapWithKeys(fn (string $label, string $key): array => [
            $key => (int) round((float) $input[$key] * 100),
        ])->all();
        if ($type === 'competition' && $prices['competition_routine_package'] <= $prices['competition_routine_video']) {
            throw ValidationException::withMessages([
                'competition_routine_package' => 'The package price must exceed its video component.',
            ]);
        }

        return DB::transaction(function () use ($priceList, $actor, $input, $type, $prices): MediaPriceList {
            $priceList ??= new MediaPriceList(['created_by_user_id' => $actor->id]);
            if ($priceList->exists && $priceList->gallery_type !== $type) {
                throw ValidationException::withMessages(['gallery_type' => 'The gallery type of an existing price list cannot be changed.']);
            }
            $makeDefault = (bool) ($input['is_default'] ?? false);
            if ($priceList->is_default && ! ($input['is_active'] ?? false)) {
                throw ValidationException::withMessages(['is_active' => 'Choose another automatic default before deactivating this price list.']);
            }
            if ($makeDefault) {
                MediaPriceList::query()->where('gallery_type', $type)->where('is_default', true)
                    ->when($priceList->exists, fn ($query) => $query->where('id', '!=', $priceList->id))
                    ->update(['is_default' => false]);
            }
            $priceList->fill([
                'name' => $input['name'], 'gallery_type' => $type, 'prices' => $prices,
                'gst_rate_basis_points' => (int) round((float) $input['gst_rate'] * 100),
                'taxable_products' => array_values(array_intersect(array_keys(self::PRODUCTS[$type]), $input['gst_applicable'] ?? [])),
                'is_default' => $makeDefault || $priceList->is_default
                    || (! $priceList->exists && ! MediaPriceList::where('gallery_type', $type)->where('is_default', true)->exists()),
                'is_active' => $makeDefault || (bool) ($input['is_active'] ?? false),
            ])->save();

            return $priceList->fresh();
        });
    }

    public function assign(string $target, ?MediaPriceList $priceList): void
    {
        [$kind, $id] = explode(':', $target, 2);
        $model = $this->assignmentModel($kind, (int) $id);
        $type = $this->assignmentType($kind, $model);
        if ($priceList && (! $priceList->is_active || $priceList->gallery_type !== $type)) {
            throw ValidationException::withMessages(['media_price_list_id' => 'Choose an active price list for the same gallery type.']);
        }
        $model->update(['media_price_list_id' => $priceList?->id]);
    }

    public function competition(SchedulingEvent $event, ?string $itemUuid = null): MediaPriceList
    {
        $this->ensureDefaults();
        if ($itemUuid) {
            $routine = ProgrammeNode::query()->where('uuid', $itemUuid)->where('kind', 'item')
                ->whereHas('programme', fn ($query) => $query->where('scheduling_event_id', $event->id))
                ->with('priceList')->first();
            if ($routine?->priceList?->is_active && $routine->priceList->gallery_type === 'competition') {
                return $routine->priceList;
            }
            $collection = MediaCollection::query()->where('competition_id', $event->id)
                ->where('metadata->item_uuid', $itemUuid)->whereNotNull('media_price_list_id')->with('priceList')->first();
            if ($collection?->priceList?->is_active && $collection->priceList->gallery_type === 'competition') {
                return $collection->priceList;
            }
        }
        $event->loadMissing('priceList');

        return $event->priceList?->is_active && $event->priceList->gallery_type === 'competition'
            ? $event->priceList
            : MediaPriceList::query()->where('gallery_type', 'competition')->where('is_default', true)->where('is_active', true)->firstOrFail();
    }

    public function concert(Concert $concert, ?MediaCollection $album = null): MediaPriceList
    {
        $this->ensureDefaults();
        $album?->loadMissing('priceList');
        if ($album?->priceList?->is_active && $album->priceList->gallery_type === 'concert') {
            return $album->priceList;
        }
        $concert->loadMissing('priceList');

        return $concert->priceList?->is_active && $concert->priceList->gallery_type === 'concert'
            ? $concert->priceList
            : MediaPriceList::query()->where('gallery_type', 'concert')->where('is_default', true)->where('is_active', true)->firstOrFail();
    }

    public function competitionPrice(SchedulingEvent $event, string $product, ?string $itemUuid = null): int
    {
        return $this->competition($event, $itemUuid)->price($product) ?? $this->legacySettings->price($product);
    }

    public function concertPrice(Concert $concert, ?MediaCollection $album = null): int
    {
        return $this->concert($concert, $album)->price('concert_photo') ?? $this->legacySettings->price('concert_photo');
    }

    private function assignmentModel(string $kind, int $id): Model
    {
        return match ($kind) {
            'competition' => SchedulingEvent::query()->where('event_type', 'competition')->findOrFail($id),
            'concert' => Concert::query()->findOrFail($id),
            'collection' => MediaCollection::query()->where(fn ($query) => $query->whereNotNull('competition_id')->orWhereNotNull('concert_id'))->findOrFail($id),
            'routine' => ProgrammeNode::query()->where('kind', 'item')->findOrFail($id),
            default => abort(404),
        };
    }

    private function assignmentType(string $kind, Model $model): string
    {
        if ($kind === 'competition' || $kind === 'routine') {
            return 'competition';
        }
        if ($kind === 'concert') {
            return 'concert';
        }

        return $model->competition_id ? 'competition' : 'concert';
    }
}
