<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateCrewMobileProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
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
            'address' => ['required', 'array'],
            'address.line_1' => ['required', 'string', 'max:255'],
            'address.line_2' => ['nullable', 'string', 'max:255'],
            'address.suburb' => ['required', 'string', 'max:255'],
            'address.state' => ['required', 'string', 'max:50'],
            'address.postcode' => ['required', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:255'],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:255'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:50'],
            'abn' => ['nullable', 'string', 'max:30'],
            'dietary_requirements' => ['nullable', 'string', 'max:5000'],
            'medical_information' => ['nullable', 'string', 'max:5000'],
            'compliance' => ['required', 'array'],
            'compliance.working_with_children_number' => ['required', 'string', 'max:100'],
            'compliance.working_with_children_expiry' => ['required', 'date'],
            'payment_details' => ['sometimes', 'array'],
            'payment_details.account_name' => ['nullable', 'string', 'max:255'],
            'payment_details.bank_name' => ['nullable', 'string', 'max:255'],
            'payment_details.bsb' => ['nullable', 'string', 'max:20'],
            'payment_details.account_number' => ['nullable', 'string', 'max:30'],
            'password' => [Rule::requiredIf(fn (): bool => array_key_exists('payment_details', $this->all())), 'nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (array_key_exists('payment_details', $this->all()) && ! Hash::check((string) $value, $this->user()->password)) {
                    $fail('The password is incorrect.');
                }
            }],
            'vehicles' => ['sometimes', 'array', 'max:10'],
            'vehicles.*.uuid' => ['nullable', 'uuid'],
            'vehicles.*.make' => ['required', 'string', 'max:100'],
            'vehicles.*.model' => ['required', 'string', 'max:100'],
            'vehicles.*.registration' => ['required', 'string', 'max:30'],
            'vehicles.*.colour' => ['nullable', 'string', 'max:50'],
            'vehicles.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, mixed> */
    public function profileData(): array
    {
        $validated = $this->validated();
        $address = $validated['address'];
        $emergency = $validated['emergency_contact'] ?? [];
        $compliance = $validated['compliance'];
        $data = collect($validated)->except(['address', 'emergency_contact', 'compliance', 'payment_details', 'password'])->all() + [
            'address_line_1' => $address['line_1'],
            'address_line_2' => $address['line_2'] ?? null,
            'suburb' => $address['suburb'],
            'state' => $address['state'],
            'postcode' => $address['postcode'],
            'emergency_contact_name' => $emergency['name'] ?? null,
            'emergency_contact_relationship' => $emergency['relationship'] ?? null,
            'emergency_contact_phone' => $emergency['phone'] ?? null,
            'working_with_children_number' => $compliance['working_with_children_number'],
            'working_with_children_expiry' => $compliance['working_with_children_expiry'],
        ];

        if (array_key_exists('payment_details', $validated)) {
            $payment = $validated['payment_details'];
            $data += [
                'bank_account_name' => $payment['account_name'] ?? null,
                'bank_name' => $payment['bank_name'] ?? null,
                'bank_bsb' => $payment['bsb'] ?? null,
                'bank_account_number' => $payment['account_number'] ?? null,
            ];
        }

        return $data;
    }
}
