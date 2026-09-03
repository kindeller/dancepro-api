<?php

namespace App\Features\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrewMobileLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'digits:6', 'prohibits:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:255', 'prohibits:two_factor_code'],
        ];
    }
}
