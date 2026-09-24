<?php

namespace App\Features\Media\Models;

use Illuminate\Database\Eloquent\Model;

class MediaCommerceSetting extends Model
{
    protected $fillable = ['settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
