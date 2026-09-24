<?php

namespace App\Features\Programmes\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionVideoBatch extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function files(): HasMany
    {
        return $this->hasMany(CompetitionVideoFile::class);
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
