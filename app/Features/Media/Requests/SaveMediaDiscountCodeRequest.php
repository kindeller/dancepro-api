<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMediaDiscountCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $type = $this->input('discount_type', 'percent');
        $value = $this->input('discount_value', $type === 'fixed' ? $this->input('amount') : $this->input('percent'));
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'discount_type' => $type,
            'discount_value' => $value,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        $discountValueRules = $this->input('discount_type') === 'fixed'
            ? ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2']
            : ['required', 'integer', 'between:1,90'];

        return [
            'code' => ['required', 'regex:/\A[A-Z0-9-]{4,32}\z/', Rule::unique('media_discount_codes', 'code')->ignore($this->route('discountCode')?->id)],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => $discountValueRules,
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'enabled' => ['sometimes', 'boolean'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_redemptions_per_customer' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
