<?php

namespace App\Features\Studios\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['studio_id', 'name', 'role', 'emails', 'phone', 'position'])]
class StudioContact extends Model
{
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function emailString(): string
    {
        return implode(', ', $this->emailAddresses());
    }

    /** @return list<string> */
    public function emailAddresses(): array
    {
        return $this->emails ?? [];
    }

    protected function casts(): array
    {
        return ['emails' => 'array'];
    }
}
