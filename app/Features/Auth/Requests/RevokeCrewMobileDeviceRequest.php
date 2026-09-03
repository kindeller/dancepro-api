<?php

namespace App\Features\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class RevokeCrewMobileDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Hash::check((string) $value, $this->user()->password)) {
                    $fail('The password is incorrect.');
                }
            }],
        ];
    }
}
