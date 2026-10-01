<?php

namespace App\Features\Concerts\Models;

use App\Features\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcertFamilyVideo extends Model
{
    protected $guarded = ['id'];

    public function share(): BelongsTo
    {
        return $this->belongsTo(ConcertFamilyShare::class, 'concert_family_share_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    protected function casts(): array
    {
        return ['last_played_at' => 'datetime', 'playback_completed_at' => 'datetime'];
    }
}
