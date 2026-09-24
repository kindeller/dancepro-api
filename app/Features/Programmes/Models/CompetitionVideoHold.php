<?php

namespace App\Features\Programmes\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionVideoHold extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_held' => 'boolean'];
    }
}
