<?php

namespace App\Features\Website\Models;

use Illuminate\Database\Eloquent\Model;

class WebsitePage extends Model
{
    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return ['draft' => 'array', 'published' => 'array', 'published_at' => 'datetime'];
    }
}
