<?php

namespace App\Features\Crew\Models;

use App\Features\Crew\Support\ContractSignatureRecordingMethod;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['crew_contract_signature_id', 'previous_status', 'previous_signed_at', 'new_status', 'new_signed_at', 'signed_name', 'signer_ip', 'signer_user_agent', 'contract_checksum', 'consent_text', 'recording_method', 'recorded_by_user_id', 'recorded_at', 'recording_note'])]
class CrewContractSignatureEvent extends Model
{
    public function signature(): BelongsTo
    {
        return $this->belongsTo(CrewContractSignature::class, 'crew_contract_signature_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'previous_status' => CrewContractSignatureStatus::class,
            'previous_signed_at' => 'datetime',
            'new_status' => CrewContractSignatureStatus::class,
            'new_signed_at' => 'datetime',
            'recording_method' => ContractSignatureRecordingMethod::class,
            'recorded_at' => 'datetime',
        ];
    }
}
