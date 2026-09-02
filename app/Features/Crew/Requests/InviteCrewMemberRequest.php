<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteCrewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['send_invitation' => $this->boolean('send_invitation')]);
    }

    public function rules(): array
    {
        return [
            'preferred_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'send_invitation' => ['required', 'boolean'],
        ];
    }
}
