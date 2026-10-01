<?php

namespace App\Features\Website\Models;

use App\Features\Crew\Models\CrewProfile;
use Illuminate\Database\Eloquent\Model;

class WebsiteBiography extends Model
{
    protected $guarded = ['id'];

    public function crewProfile()
    {
        return $this->belongsTo(CrewProfile::class);
    }

    protected function casts(): array
    {
        return ['draft' => 'array', 'published' => 'array', 'published_at' => 'datetime'];
    }
}
