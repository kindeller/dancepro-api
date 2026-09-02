<?php

namespace App\Features\Operations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['checklist_template_id', 'instruction', 'sort_order'])]
class ChecklistTemplateItem extends Model
{
    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(AssignmentChecklistCompletion::class);
    }
}
