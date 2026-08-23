<?php

namespace App\Features\Concerts\Models;

use App\Features\Concerts\Support\ConcertAccessMethod;
use App\Models\User;
use Database\Factories\ConcertAccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concert_id', 'user_id', 'concert_access_grant_id', 'access_method', 'accessed_at', 'last_seen_at', 'session_identifier', 'student_name', 'ip_address', 'user_agent', 'referrer', 'was_successful', 'failure_reason'])]
class ConcertAccess extends Model
{
    /** @use HasFactory<ConcertAccessFactory> */
    use HasFactory;

    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(ConcertAccessGrant::class, 'concert_access_grant_id');
    }

    protected function casts(): array
    {
        return ['access_method' => ConcertAccessMethod::class, 'accessed_at' => 'datetime', 'last_seen_at' => 'datetime', 'was_successful' => 'boolean'];
    }

    protected static function newFactory(): ConcertAccessFactory
    {
        return ConcertAccessFactory::new();
    }
}
