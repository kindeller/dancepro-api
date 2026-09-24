<?php

namespace App\Features\Programmes\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeExtraction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result' => 'array', 'draft_version' => 'integer'];
    }
}
