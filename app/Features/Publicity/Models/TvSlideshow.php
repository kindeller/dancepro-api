<?php

namespace App\Features\Publicity\Models;

use Illuminate\Database\Eloquent\Model;

class TvSlideshow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'photo_seconds' => 'integer', 'promotion_every' => 'integer'];
    }
}
