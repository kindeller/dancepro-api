<?php

namespace App\Features\Payments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrepareCustomerCheckoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['discount_code' => strtoupper(trim((string) $this->input('discount_code')))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() === true;
    }

    public function rules(): array
    {
        return [
            'discount_code' => ['nullable', 'regex:/\A[A-Z0-9-]{4,32}\z/'],
            'use_account_credit' => ['sometimes', 'boolean'],
        ];
    }
}
