<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerCompetitionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'size' => ['nullable', Rule::in(['thumbnail', 'cover'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'tab' => ['nullable', Rule::in(['overview', 'events', 'files', 'messages'])],
            'focus_kind' => ['nullable', Rule::in(['competition', 'concert'])],
            'focus_event' => ['nullable', 'uuid'],
            'routine' => ['nullable', 'uuid'],
        ];
    }
}
