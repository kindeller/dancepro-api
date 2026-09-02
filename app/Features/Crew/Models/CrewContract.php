<?php

namespace App\Features\Crew\Models;

use App\Features\Crew\Support\CrewContractStatus;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\CrewContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'name', 'version', 'status', 'effective_from', 'content', 'document_disk', 'document_path', 'document_checksum', 'created_by_user_id'])]
class CrewContract extends Model
{
    /** @use HasFactory<CrewContractFactory> */
    use HasFactory, HasPublicUuid;

    public function signatures(): HasMany
    {
        return $this->hasMany(CrewContractSignature::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => CrewContractStatus::class,
            'effective_from' => 'date',
        ];
    }

    protected static function newFactory(): CrewContractFactory
    {
        return CrewContractFactory::new();
    }
}
