<?php

namespace App\Features\Programmes\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionProgrammeAddition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['node' => 'array'];
    }
}
