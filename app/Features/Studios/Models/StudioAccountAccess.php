<?php

namespace App\Features\Studios\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'studio_id', 'user_id', 'email', 'role', 'status', 'invited_by_user_id', 'invited_at', 'activated_at', 'revoked_at'])]
class StudioAccountAccess extends Model
{
    use HasPublicUuid;

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    protected function casts(): array
    {
        return ['invited_at' => 'datetime', 'activated_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
