<?php

namespace App\Features\Crew\Requests;

use App\Shared\Support\AccountName;
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
        if (! $this->filled('existing_email')) {
            $this->merge(AccountName::input($this->only(['first_name', 'last_name'])));
            $this->merge(['send_invitation' => $this->boolean('send_invitation')]);
        }
    }

    public function rules(): array
    {
        return [
            'existing_email' => ['nullable', 'email', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'first_name' => [Rule::requiredIf($this->has('first_name') || $this->has('last_name')), Rule::prohibitedIf($this->filled('existing_email')), 'string', 'max:255'],
            'last_name' => ['nullable', Rule::prohibitedIf($this->filled('existing_email')), 'string', 'max:255'],
            'preferred_name' => ['required_without:existing_email', Rule::prohibitedIf($this->filled('existing_email')), 'string', 'max:255'],
            'email' => ['required_without:existing_email', Rule::prohibitedIf($this->filled('existing_email')), 'email', 'max:255', Rule::unique('users', 'email')],
            'send_invitation' => ['required_without:existing_email', Rule::prohibitedIf($this->filled('existing_email')), 'boolean'],
        ];
    }
}
