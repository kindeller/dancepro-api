<?php

namespace App\Features\Concerts\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcertFamilyDownload extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(ConcertFamilyVideo::class, 'concert_family_video_id');
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
