<?php

namespace App\Features\Media\Models;

use App\Features\Studios\Models\Studio;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ConcertPhotoReleaseGroup extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function watermark(): BelongsTo
    {
        return $this->belongsTo(MediaWatermark::class, 'media_watermark_id');
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

        $collectionIds = $this->relationLoaded('collections')
            ? $this->collections->pluck('id')
            : $this->collections()->pluck('media_collections.id');

        return MediaAsset::query()->whereIn('media_collection_id', $collectionIds)
            ->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)
            ->where('metadata->is_top_pic', true)->orderBy('sort_order')->orderBy('id')->first();
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(MediaCollection::class, 'concert_photo_release_group_collections')->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
