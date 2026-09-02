<?php

namespace App\Features\Crew\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'name', 'icon', 'design', 'default_message', 'is_active'])]
class RecognitionType extends Model
{
    use HasPublicUuid;

    public function recognitions(): HasMany
    {
        return $this->hasMany(CrewRecognition::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
