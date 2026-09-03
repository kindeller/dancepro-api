<?php

namespace App\Features\Timesheets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrewMobileInvoiceSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return [
            'assignment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'assignment_ids.*' => ['required', 'uuid', 'distinct'],
            'starting_invoice_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'invoice_style' => ['required', 'in:classic,minimal,modern'],
        ];
    }
}
