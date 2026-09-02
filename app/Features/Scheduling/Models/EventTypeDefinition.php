<?php

namespace App\Features\Scheduling\Models;

use App\Features\Scheduling\Support\SchedulingEventType;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['uuid', 'code', 'name', 'system_category', 'description', 'is_active'])]
class EventTypeDefinition extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'system_category' => SchedulingEventType::class,
            'is_active' => 'boolean',
        ];
    }
}
