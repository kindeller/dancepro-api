<?php

namespace App\Features\Media\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'user_id', 'media_asset_id', 'action', 'was_successful', 'context', 'created_at'])]
class MediaIngestEvent extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    protected function casts(): array
    {
        return [
            'was_successful' => 'boolean',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
