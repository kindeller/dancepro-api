<?php

namespace App\Features\Tracking\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['uuid', 'task_key', 'name', 'subject', 'body', 'is_active'])]
class ConcertTrackerEmailTemplate extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
