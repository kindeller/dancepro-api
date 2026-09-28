<?php

namespace App\Features\Concerts\Models;

use Illuminate\Database\Eloquent\Model;

class ConcertDirectorReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['feedback' => 'array', 'revision' => 'integer', 'corrections_completed_at' => 'datetime',
            'corrections_uploaded_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
