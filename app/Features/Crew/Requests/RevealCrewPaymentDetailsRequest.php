<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevealCrewPaymentDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }
}
