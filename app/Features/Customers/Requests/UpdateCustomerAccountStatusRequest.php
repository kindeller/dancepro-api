<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['active', 'paused', 'cancelled'])], 'reason' => ['required', 'string', 'max:500']];
    }
}
