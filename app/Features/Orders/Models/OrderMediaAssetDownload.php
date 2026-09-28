<?php

namespace App\Features\Orders\Models;

use App\Features\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMediaAssetDownload extends Model
{
    protected $guarded = ['id'];

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(OrderMediaEntitlement::class, 'order_media_entitlement_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'download_count' => 'integer', 'bytes_requested' => 'integer',
            'first_download_at' => 'datetime', 'last_download_at' => 'datetime',
        ];
    }
}
