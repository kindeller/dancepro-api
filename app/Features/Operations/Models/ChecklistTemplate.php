<?php

namespace App\Features\Operations\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'name', 'event_type', 'event_type_definition_id', 'role_code', 'is_active'])]
class ChecklistTemplate extends Model
{
    use HasPublicUuid;

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
