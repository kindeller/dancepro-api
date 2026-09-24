<?php

namespace App\Features\Programmes\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class ProgrammeUploadPlan extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
