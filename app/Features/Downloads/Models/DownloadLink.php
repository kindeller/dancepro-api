<?php

namespace App\Features\Downloads\Models;

use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Models\User;
use Database\Factories\DownloadLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'generated_by_user_id',
    'storage_disk',
    'storage_key',
    'original_filename',
    'purpose',
    'token_hash',
    'status',
    'expires_at',
    'first_opened_at',
    'last_opened_at',
    'download_count',
    'revoked_at',
    'revoked_by_user_id',
    'revoke_reason',
    'notes',
])]
class DownloadLink extends Model
{
    /** @use HasFactory<DownloadLinkFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @return HasMany<DownloadAccess, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(DownloadAccess::class);
    }

    public function isRevoked(): bool
    {
        return $this->status === DownloadLinkStatus::REVOKED || $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'download_count' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function newFactory(): DownloadLinkFactory
    {
        return DownloadLinkFactory::new();
    }
}
