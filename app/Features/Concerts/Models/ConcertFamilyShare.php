<?php

namespace App\Features\Concerts\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConcertFamilyShare extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['player_hash', 'recipient_hash', 'code_hash', 'challenge_session_hash', 'verified_session_hash'];

    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ConcertFamilyVideo::class);
    }

    protected function casts(): array
    {
        return array_fill_keys(['player_expires_at', 'contact_redacted_at', 'invitation_expires_at', 'accepted_at', 'expires_at', 'revoked_at', 'code_sent_at', 'code_expires_at', 'last_activity_at'], 'datetime');
    }
}
