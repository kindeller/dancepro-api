<?php

namespace App\Features\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'key', 'request_method', 'request_target', 'request_hash', 'response_status', 'response_body', 'response_headers', 'completed_at', 'expires_at'])]
class ApiIdempotencyRecord extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'response_body' => 'encrypted',
            'response_headers' => 'encrypted:array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
