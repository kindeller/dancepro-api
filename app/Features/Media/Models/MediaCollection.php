<?php

namespace App\Features\Media\Models;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaType;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\MediaCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'concert_id', 'competition_id', 'name', 'media_type', 'catalogue_mode', 'status', 'visibility', 'storage_disk', 'storage_prefix', 'manifest_key', 'sort_order', 'published_at', 'archived_at', 'metadata'])]
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
