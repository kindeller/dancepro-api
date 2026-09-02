<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangeOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->crewProfile !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
