<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerMobileLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'], 'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'two_factor_code' => ['nullable', 'digits:6', 'prohibits:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:255', 'prohibits:two_factor_code'],
        ];
    }
}
