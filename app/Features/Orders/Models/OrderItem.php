<?php

namespace App\Features\Orders\Models;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'media_collection_id', 'media_asset_id', 'snapshot_storage_disk', 'snapshot_storage_key', 'snapshot_filename', 'snapshot_display_name', 'item_type', 'quantity', 'unit_price_amount', 'total_price_amount', 'metadata'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function mediaCollection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function downloadLinks(): HasMany
    {
        return $this->hasMany(DownloadLink::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_amount' => 'integer', 'total_price_amount' => 'integer', 'metadata' => 'array'];
    }

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }
}
