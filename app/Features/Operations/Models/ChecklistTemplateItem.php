<?php

namespace App\Features\Operations\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'checklist_template_id', 'instruction', 'sort_order'])]
class ChecklistTemplateItem extends Model
{
    use HasPublicUuid;

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(AssignmentChecklistCompletion::class);
    }
}
