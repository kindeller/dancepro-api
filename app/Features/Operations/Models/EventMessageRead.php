<?php

namespace App\Features\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_message_id', 'user_id', 'read_at'])]
class EventMessageRead extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(EventMessage::class, 'event_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
