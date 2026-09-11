<?php

namespace App\Features\Timesheets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceStyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->canAccessCrew() ?? false) && $this->user()->crewProfile !== null;
    }

    public function rules(): array
    {
        return ['invoice_style' => ['required', 'in:classic,minimal,modern']];
    }
}
