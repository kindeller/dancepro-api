<?php

namespace App\Features\Tracking\Models;

use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioConversationThread extends Model
{
    protected $guarded = ['id'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
