<?php

namespace App\Features\Payments\Requests;

use App\Features\Customers\Support\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantCustomerCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('type', UserType::Customer->value)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'credit_type' => ['required', Rule::in(['reward', 'gift', 'purchased'])],
            'note' => ['required', 'string', 'max:500'],
        ];
    }
}
