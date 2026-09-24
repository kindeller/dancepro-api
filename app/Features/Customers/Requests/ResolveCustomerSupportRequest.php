<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveCustomerSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'declined', 'resolved'])],
            'extension_days' => ['nullable', 'required_if:decision,approved', 'integer', 'between:1,365'],
            'response_note' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
