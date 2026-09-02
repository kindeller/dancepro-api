<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignCrewContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->crewProfile !== null;
    }

    public function rules(): array
    {
        return [
            'signed_name' => ['required', 'string', 'max:255'],
            'accept_contract' => ['accepted'],
            'password' => ['required', 'current_password:web'],
        ];
    }
}
