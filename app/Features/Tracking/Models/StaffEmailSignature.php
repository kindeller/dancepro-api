<?php

namespace App\Features\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'display_name', 'position', 'phone', 'sign_off', 'is_enabled'])]
class StaffEmailSignature extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
