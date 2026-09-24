<?php

namespace App\Features\Programmes\Models;

use App\Features\Studios\Models\Studio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionRoutineStudio extends Model
{
    protected $guarded = ['id'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
