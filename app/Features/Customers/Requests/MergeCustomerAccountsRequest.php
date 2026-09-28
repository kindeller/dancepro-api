<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeCustomerAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['source_customer_id' => ['required', 'integer', 'different:target_customer_id'], 'target_customer_id' => ['required', 'integer'], 'confirmation_email' => ['required', 'email'], 'reason' => ['required', 'string', 'max:500']];
    }
}
