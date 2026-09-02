<?php

namespace App\Features\Crew\Requests;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Support\CrewRoleQualificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCrewProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $qualifications = collect($this->input('qualifications', []))
            ->filter(fn ($qualification) => filled($qualification['status'] ?? null))
            ->all();
        $vehicles = collect($this->input('vehicles', []))
            ->filter(fn ($vehicle) => collect($vehicle)->except('uuid')->contains(fn ($value) => filled($value)))
            ->values()
            ->all();

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'qualifications' => $qualifications,
            'vehicles' => $vehicles,
        ]);
    }

    public function rules(): array
    {
        /** @var CrewProfile|null $crewProfile */
        $crewProfile = $this->route('crewProfile');

        return [
            'preferred_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($crewProfile?->user_id)],
            'phone' => ['required', 'string', 'max:50'],
            'shirt_size' => ['nullable', 'string', 'max:50'],
            'jacket_size' => ['nullable', 'string', 'max:50'],
            'commencement_date' => ['required', 'date', 'before_or_equal:today'],
            'is_active' => ['required', 'boolean'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'suburb' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'abn' => ['nullable', 'string', 'max:30'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bsb' => ['nullable', 'string', 'max:20'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'super_fund_name' => ['nullable', 'string', 'max:255'],
            'super_member_number' => ['nullable', 'string', 'max:100'],
            'dietary_requirements' => ['nullable', 'string', 'max:5000'],
            'medical_information' => ['nullable', 'string', 'max:5000'],
            'drivers_licence_number' => ['nullable', 'string', 'max:100'],
            'working_with_children_number' => ['nullable', 'string', 'max:100'],
            'working_with_children_expiry' => ['nullable', 'date'],
            'first_aid_details' => ['nullable', 'string', 'max:1000'],
            'first_aid_expiry' => ['nullable', 'date'],
            'owned_equipment' => ['nullable', 'string', 'max:5000'],
            'usual_travel_area' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'vehicles' => ['nullable', 'array', 'max:10'],
            'vehicles.*.uuid' => ['nullable', 'uuid'],
            'vehicles.*.make' => ['required', 'string', 'max:100'],
            'vehicles.*.model' => ['required', 'string', 'max:100'],
            'vehicles.*.registration' => ['required', 'string', 'max:30'],
            'vehicles.*.colour' => ['nullable', 'string', 'max:50'],
            'vehicles.*.notes' => ['nullable', 'string', 'max:1000'],
            'qualifications' => ['nullable', 'array'],
            'qualifications.*' => ['array'],
            'qualifications.*.status' => ['required', Rule::enum(CrewRoleQualificationStatus::class)],
            'qualifications.*.effective_from' => ['nullable', 'date'],
            'qualifications.*.effective_until' => ['nullable', 'date', 'after_or_equal:qualifications.*.effective_from'],
            'qualifications.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
