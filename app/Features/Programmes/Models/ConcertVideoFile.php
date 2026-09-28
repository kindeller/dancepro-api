<?php

namespace App\Features\Programmes\Models;

use App\Features\Media\Models\MediaAsset;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcertVideoFile extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ConcertVideoBatch::class, 'concert_video_batch_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    protected function casts(): array
    {
        return ['byte_count' => 'integer', 'verified_at' => 'datetime'];
    }
}
