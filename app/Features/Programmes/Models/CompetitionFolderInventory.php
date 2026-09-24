<?php

namespace App\Features\Programmes\Models;

use App\Features\Crew\Models\CrewRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionFolderInventory extends Model
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class, 'crew_role_id');
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['folders' => 'array'];
    }
}
