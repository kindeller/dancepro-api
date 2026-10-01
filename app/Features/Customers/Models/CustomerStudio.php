<?php

namespace App\Features\Customers\Models;

use App\Features\Studios\Models\Studio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerStudio extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['access_verified_at' => 'datetime', 'membership_confirmed_at' => 'datetime'];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
