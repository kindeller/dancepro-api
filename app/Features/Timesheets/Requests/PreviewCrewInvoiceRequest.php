<?php

namespace App\Features\Timesheets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewCrewInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->crewProfile !== null;
    }

    public function rules(): array
    {
        return ['entry_ids' => ['required', 'array', 'min:1'], 'entry_ids.*' => ['required', 'integer'], 'starting_invoice_number' => ['nullable', 'integer', 'min:1', 'max:999999999'], 'invoice_style' => ['required', 'in:classic,minimal,modern']];
    }
}
