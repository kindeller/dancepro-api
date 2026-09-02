<?php

namespace App\Features\Crew\Models;

use App\Features\Crew\Support\ContractSignatureRecordingMethod;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\CrewContractSignatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'crew_contract_id', 'crew_profile_id', 'status', 'signed_at', 'signed_name', 'signer_ip', 'signer_user_agent', 'contract_checksum', 'consent_text', 'recording_method', 'recorded_by_user_id', 'recorded_at', 'recording_note'])]
class CrewContractSignature extends Model
{
    /** @use HasFactory<CrewContractSignatureFactory> */
    use HasFactory, HasPublicUuid;

    public function contract(): BelongsTo
    {
        return $this->belongsTo(CrewContract::class, 'crew_contract_id');
    }

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CrewContractSignatureEvent::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CrewContractSignatureStatus::class,
            'signed_at' => 'datetime',
            'recording_method' => ContractSignatureRecordingMethod::class,
            'recorded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CrewContractSignatureFactory
    {
        return CrewContractSignatureFactory::new();
    }
}
