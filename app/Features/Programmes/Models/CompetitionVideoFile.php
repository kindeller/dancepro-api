<?php

namespace App\Features\Programmes\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionVideoFile extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CompetitionVideoBatch::class, 'competition_video_batch_id');
    }

    protected function casts(): array
    {
        return ['byte_count' => 'integer', 'verified_at' => 'datetime'];
    }
}
