<?php

namespace App\Features\Media\Models;

use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\OrderItem;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'media_collection_id', 'media_type', 'storage_disk', 'storage_key', 'original_filename', 'display_name', 'status', 'is_visible', 'sort_order', 'size_bytes', 'duration_seconds', 'mime_type', 'extension', 'thumbnail_storage_disk', 'thumbnail_storage_key', 'verified_at', 'missing_at', 'archived_at', 'metadata'])]
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'media_collection_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MediaAssetLocation::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class, 'status' => MediaAssetStatus::class, 'is_visible' => 'boolean',
            'sort_order' => 'integer', 'size_bytes' => 'integer', 'duration_seconds' => 'integer',
            'verified_at' => 'datetime', 'missing_at' => 'datetime', 'archived_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    protected static function newFactory(): MediaAssetFactory
    {
        return MediaAssetFactory::new();
    }
}
