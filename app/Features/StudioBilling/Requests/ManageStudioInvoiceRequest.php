<?php

namespace App\Features\StudioBilling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageStudioInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'store' => ['kind' => ['required', 'in:video,custom'], 'is_test' => ['sometimes', 'boolean']],
            'update' => ['send_invoice' => ['sometimes', 'boolean'], 'lines' => ['required', 'array', 'min:1', 'max:20'], 'lines.*.description' => ['required', 'string', 'max:300'], 'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'], 'lines.*.unit_amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'discount_amount' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'due_date' => ['sometimes', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:3000']],
            'recordPayment' => ['amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'reference' => ['required', 'string', 'max:200'], 'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'idempotency_key' => ['required', 'uuid']],
            'void','reverse' => ['reason' => ['required', 'string', 'max:1000']],
            'saveSettings' => ['business_name' => ['required', 'string', 'max:200'], 'abn' => ['required', 'regex:/^\d{11}$/'], 'address' => ['nullable', 'string', 'max:1000'], 'email' => ['required', 'email', 'max:255'], 'bank_name' => ['nullable', 'required_with:bank_bsb,bank_account', 'string', 'max:200'], 'bank_bsb' => ['nullable', 'required_with:bank_name,bank_account', 'regex:/^\d{6}$/'], 'bank_account' => ['nullable', 'required_with:bank_name,bank_bsb', 'regex:/^\d{4,12}$/']],
            default => [],
        };
    }
}
