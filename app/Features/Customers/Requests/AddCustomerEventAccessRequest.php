<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCustomerEventAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['event_kind' => ['required', Rule::in(['competition', 'concert'])], 'event_uuid' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500']];
    }
}
