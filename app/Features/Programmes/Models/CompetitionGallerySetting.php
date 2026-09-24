<?php

namespace App\Features\Programmes\Models;

use App\Features\Media\Models\MediaWatermark;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionGallerySetting extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['login_code_hash'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function watermark(): BelongsTo
    {
        return $this->belongsTo(MediaWatermark::class, 'media_watermark_id');
    }
}
