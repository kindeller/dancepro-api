<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class SignCrewMobileContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return [
            'signed_name' => ['required', 'string', 'max:255'],
            'accept_contract' => ['accepted'],
            'password' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Hash::check((string) $value, $this->user()->password)) {
                    $fail('The password is incorrect.');
                }
            }],
        ];
    }
}
