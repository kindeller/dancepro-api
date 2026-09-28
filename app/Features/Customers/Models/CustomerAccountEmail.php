<?php

namespace App\Features\Customers\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccountEmail extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'metadata' => 'array'];
    }
}
