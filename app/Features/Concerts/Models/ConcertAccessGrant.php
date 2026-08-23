<?php

namespace App\Features\Concerts\Models;

use App\Features\Concerts\Support\ConcertAccessGrantSource;
use App\Features\Concerts\Support\ConcertAccessGrantStatus;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\ConcertAccessGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'concert_id', 'user_id', 'email', 'source', 'status', 'granted_by_user_id', 'first_accessed_at', 'last_accessed_at', 'claimed_at', 'expires_at', 'revoked_at', 'revoke_reason', 'metadata'])]
class ConcertAccessGrant extends Model
{
    /** @use HasFactory<ConcertAccessGrantFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(ConcertAccess::class);
    }

    protected function casts(): array
    {
        return [
            'source' => ConcertAccessGrantSource::class, 'status' => ConcertAccessGrantStatus::class,
            'first_accessed_at' => 'datetime', 'last_accessed_at' => 'datetime', 'claimed_at' => 'datetime',
            'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    protected static function newFactory(): ConcertAccessGrantFactory
    {
        return ConcertAccessGrantFactory::new();
    }
}
