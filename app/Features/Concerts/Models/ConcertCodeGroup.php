<?php

namespace App\Features\Concerts\Models;

use App\Features\Studios\Models\Studio;
use App\Shared\Models\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class ConcertCodeGroup extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function concerts(): HasMany
    {
        return $this->hasMany(Concert::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ConcertCodeGroupChange::class);
    }

    public function matches(string $code): bool
    {
        return Hash::check(strtoupper(trim($code)), $this->code_hash);
    }

    public function streamEndsAt(): ?CarbonImmutable
    {
        return $this->released_at?->toImmutable()->addMonthsNoOverflow(6);
    }

    public function downloadEndsAt(): ?CarbonImmutable
    {
        return $this->released_at?->toImmutable()->addMonthsNoOverflow(12);
    }

    protected function casts(): array
    {
        return ['year' => 'integer', 'version' => 'integer', 'released_at' => 'datetime'];
    }
}
