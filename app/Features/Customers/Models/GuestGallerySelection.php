<?php

namespace App\Features\Customers\Models;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestGallerySelection extends Model
{
    protected $guarded = ['id'];

    public function access(): BelongsTo
    {
        return $this->belongsTo(GuestGalleryAccess::class, 'guest_gallery_access_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class);
    }

    protected function casts(): array
    {
        return ['is_favourite' => 'boolean', 'in_cart' => 'boolean'];
    }
}
