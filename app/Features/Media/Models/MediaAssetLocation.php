<?php

namespace App\Features\Media\Models;

use App\Features\Media\Support\MediaAssetLocationStatus;
use Database\Factories\MediaAssetLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_asset_id', 'storage_disk', 'storage_key', 'status', 'became_active_at', 'retired_at', 'metadata'])]
class MediaAssetLocation extends Model
{
    /** @use HasFactory<MediaAssetLocationFactory> */
    use HasFactory;

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    protected function casts(): array
    {
        return ['status' => MediaAssetLocationStatus::class, 'became_active_at' => 'datetime', 'retired_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function newFactory(): MediaAssetLocationFactory
    {
        return MediaAssetLocationFactory::new();
    }
}
