<?php

namespace App\Features\Scheduling\Models;

use App\Features\Notifications\Jobs\DeliverMobilePushNotification;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'user_id', 'type', 'title', 'message', 'destination_type', 'destination_id', 'read_at'])]
class CrewNotification extends Model
{
    use HasPublicUuid;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(): ?array
    {
        return filled($this->destination_type) ? [
            'type' => $this->destination_type,
            'id' => $this->destination_id,
        ] : null;
    }

    protected static function booted(): void
    {
        static::created(fn (CrewNotification $notification) => DeliverMobilePushNotification::dispatch($notification->id)->afterCommit());
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
