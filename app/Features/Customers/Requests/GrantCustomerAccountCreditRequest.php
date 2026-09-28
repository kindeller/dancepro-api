<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantCustomerAccountCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['amount' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'], 'credit_type' => ['required', Rule::in(['reward', 'gift', 'purchased'])], 'note' => ['required', 'string', 'max:500']];
    }
}
