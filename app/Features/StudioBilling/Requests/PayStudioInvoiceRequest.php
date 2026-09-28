<?php

namespace App\Features\StudioBilling\Requests;

use App\Features\StudioBilling\Services\StudioInvoiceAccess;
use App\Features\StudioBilling\Services\StudioInvoiceGateway;
use Illuminate\Foundation\Http\FormRequest;

class PayStudioInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(StudioInvoiceAccess::class)->invoice($this->user(), $this->route('studio'), $this->route('studioInvoice'));

        return true;
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'balance_cents' => ['required', 'integer', 'min:1'], 'nonce' => ['required_if:payment_mode,braintree', 'nullable', 'string', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['payment_mode' => app(StudioInvoiceGateway::class)->mode()]);
    }
}
