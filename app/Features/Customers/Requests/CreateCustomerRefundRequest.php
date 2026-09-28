<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['order_item_ids' => ['required', 'array', 'min:1'], 'order_item_ids.*' => ['integer', 'distinct'], 'reason' => ['required', 'string', 'max:500'], 'revoke_access' => ['sometimes', 'boolean']];
    }
}
