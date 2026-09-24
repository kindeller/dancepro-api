<?php

namespace App\Features\Programmes\Models;

use App\Features\Media\Models\MediaPriceList;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeNode extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(MediaPriceList::class, 'media_price_list_id');
    }

    protected function casts(): array
    {
        return ['data' => 'array', 'is_active' => 'boolean'];
    }
}
