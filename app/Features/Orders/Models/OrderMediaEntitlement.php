<?php

namespace App\Features\Orders\Models;

use App\Features\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderMediaEntitlement extends Model
{
    protected $guarded = ['id'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'order_media_entitlement_assets');
    }

    protected function casts(): array
    {
        return ['ready_at' => 'datetime', 'expires_at' => 'datetime',
            'first_download_at' => 'datetime', 'last_download_at' => 'datetime',
            'download_count' => 'integer'];
    }
}
