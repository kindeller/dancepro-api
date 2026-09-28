<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRiskEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::in(['chargeback', 'dispute', 'fraud_review', 'payment_failure', 'manual_flag'])], 'order_id' => ['nullable', 'integer'], 'amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'], 'provider_reference' => ['nullable', 'string', 'max:255'], 'details' => ['required', 'string', 'max:5000']];
    }
}
