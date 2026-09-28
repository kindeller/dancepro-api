<?php

namespace App\Features\Programmes\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConcertVideoBatch extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function files(): HasMany
    {
        return $this->hasMany(ConcertVideoFile::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'studio_inclusions' => 'array'];
    }
}
