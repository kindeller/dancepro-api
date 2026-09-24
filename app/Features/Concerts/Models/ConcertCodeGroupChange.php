<?php

namespace App\Features\Concerts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcertCodeGroupChange extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'version' => 'integer'];
    }
}
