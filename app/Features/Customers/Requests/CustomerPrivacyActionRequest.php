<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerPrivacyActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['export', 'anonymize'])], 'reason' => ['required', 'string', 'max:500'], 'confirmation_email' => ['required_if:action,anonymize', 'nullable', 'email']];
    }
}
