<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnCrewProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehicles' => collect($this->input('vehicles', []))
                ->filter(fn ($vehicle) => collect($vehicle)->except('uuid')->contains(fn ($value) => filled($value)))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'preferred_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['required', 'string', 'max:50'],
            'shirt_size' => ['nullable', 'string', 'max:50'],
            'jacket_size' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'suburb' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:50'],
            'postcode' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'abn' => ['nullable', 'string', 'max:30'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bsb' => ['nullable', 'string', 'max:20'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'dietary_requirements' => ['nullable', 'string', 'max:5000'],
            'medical_information' => ['nullable', 'string', 'max:5000'],
            'working_with_children_number' => ['required', 'string', 'max:100'],
            'working_with_children_expiry' => ['required', 'date'],
            'vehicles' => ['nullable', 'array', 'max:10'],
            'vehicles.*.uuid' => ['nullable', 'uuid'],
            'vehicles.*.make' => ['required', 'string', 'max:100'],
            'vehicles.*.model' => ['required', 'string', 'max:100'],
            'vehicles.*.registration' => ['required', 'string', 'max:30'],
            'vehicles.*.colour' => ['nullable', 'string', 'max:50'],
            'vehicles.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
