<?php

namespace App\Features\Customers\Models;

use App\Features\Studios\Models\Studio;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestStudioSubscription extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['email', 'email_hash'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'consent_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}
