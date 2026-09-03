<?php

namespace App\Features\Notifications\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'user_id', 'installation_id', 'platform', 'push_token', 'push_token_hash', 'environment', 'app_version', 'device_name', 'last_registered_at', 'disabled_at'])]
class MobilePushDevice extends Model
{
    use HasPublicUuid;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'push_token' => 'encrypted',
            'last_registered_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }
}
