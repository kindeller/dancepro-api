<?php

namespace App\Features\Crew\Models;

use App\Features\Scheduling\Models\CrewAvailabilityResponse;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Training\Models\TrainingEnrolment;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Carbon\CarbonInterface;
use Database\Factories\CrewProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'user_id', 'legal_name', 'preferred_name', 'phone', 'shirt_size', 'jacket_size', 'commencement_date', 'date_of_birth', 'address_line_1', 'address_line_2', 'suburb', 'state', 'postcode', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'abn', 'bank_account_name', 'bank_name', 'bank_bsb', 'bank_account_number', 'super_fund_name', 'super_member_number', 'dietary_requirements', 'medical_information', 'drivers_licence_number', 'working_with_children_number', 'working_with_children_expiry', 'first_aid_details', 'first_aid_expiry', 'owned_equipment', 'usual_travel_area', 'profile_photo_path', 'internal_notes', 'next_invoice_number'])]
class CrewProfile extends Model
{
    /** @use HasFactory<CrewProfileFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(CrewRole::class, 'crew_role_qualifications')
            ->using(CrewRoleQualification::class)
            ->withPivot(['status', 'effective_from', 'effective_until', 'notes'])
            ->withTimestamps();
    }

    public function roleQualifications(): HasMany
    {
        return $this->hasMany(CrewRoleQualification::class);
    }

    public function contractSignatures(): HasMany
    {
        return $this->hasMany(CrewContractSignature::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CrewVehicle::class);
    }

    public function availabilityResponses(): HasMany
    {
        return $this->hasMany(CrewAvailabilityResponse::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(SchedulingShiftAssignment::class);
    }

    public function trainingEnrolments(): HasMany
    {
        return $this->hasMany(TrainingEnrolment::class);
    }

    public function recognitions(): HasMany
    {
        return $this->hasMany(CrewRecognition::class);
    }

    public function completedYearsOfService(?CarbonInterface $asOf = null): ?int
    {
        $months = $this->monthsOfService($asOf);

        return $months === null ? null : intdiv($months, 12);
    }

    public function monthsOfService(?CarbonInterface $asOf = null): ?int
    {
        if ($this->commencement_date === null) {
            return null;
        }

        $asOf ??= today();

        if ($asOf->isBefore($this->commencement_date)) {
            return 0;
        }

        return (int) floor($this->commencement_date->diffInMonths($asOf));
    }

    protected function casts(): array
    {
        return [
            'commencement_date' => 'date',
            'date_of_birth' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
            'abn' => 'encrypted',
            'bank_account_name' => 'encrypted',
            'bank_name' => 'encrypted',
            'bank_bsb' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'super_fund_name' => 'encrypted',
            'super_member_number' => 'encrypted',
            'dietary_requirements' => 'encrypted',
            'medical_information' => 'encrypted',
            'drivers_licence_number' => 'encrypted',
            'working_with_children_number' => 'encrypted',
            'working_with_children_expiry' => 'date',
            'first_aid_details' => 'encrypted',
            'first_aid_expiry' => 'date',
            'next_invoice_number' => 'integer',
        ];
    }

    protected static function newFactory(): CrewProfileFactory
    {
        return CrewProfileFactory::new();
    }
}
