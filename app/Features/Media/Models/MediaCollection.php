<?php

namespace App\Features\Media\Models;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaType;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\MediaCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'concert_id', 'competition_id', 'media_watermark_id', 'media_price_list_id', 'cover_media_asset_id', 'name', 'media_type', 'catalogue_mode', 'status', 'visibility', 'storage_disk', 'storage_prefix', 'manifest_key', 'created_by_user_id', 'idempotency_key', 'sort_order', 'published_at', 'archived_at', 'metadata'])]
class MediaCollection extends Model
{
    /** @use HasFactory<MediaCollectionFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function watermark(): BelongsTo
    {
        return $this->belongsTo(MediaWatermark::class, 'media_watermark_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(MediaPriceList::class, 'media_price_list_id');
    }

    public function coverAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    public function displayCoverAsset(): ?MediaAsset
    {
        if ($this->coverAsset?->is_visible && $this->coverAsset->status->value === 'available') {
            return $this->coverAsset;
        }

        return $this->relationLoaded('assets')
            ? $this->assets->first(fn (MediaAsset $asset): bool => $asset->media_type->value === 'photo'
                && $asset->status->value === 'available' && $asset->is_visible)
            : $this->assets()->where('media_type', 'photo')->where('status', 'available')
                ->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->first();
    }

    public function galleryFolderCoverAsset(): ?MediaAsset
    {
        $query = $this->assets()->where('media_type', 'photo')->where('status', 'available')
            ->where('is_visible', true);

        return (clone $query)->where('metadata->is_top_pic', true)->orderBy('sort_order')->orderBy('id')->first()
            ?? (clone $query)->orderBy('sort_order')->orderBy('id')->first();
    }

    public function concertPhotoReleaseGroups(): BelongsToMany
    {
        return $this->belongsToMany(ConcertPhotoReleaseGroup::class, 'concert_photo_release_group_collections')->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class, 'catalogue_mode' => MediaCatalogueMode::class,
            'status' => MediaCollectionStatus::class, 'visibility' => MediaCollectionVisibility::class,
            'sort_order' => 'integer', 'published_at' => 'datetime', 'archived_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    protected static function newFactory(): MediaCollectionFactory
    {
        return MediaCollectionFactory::new();
    }
}
