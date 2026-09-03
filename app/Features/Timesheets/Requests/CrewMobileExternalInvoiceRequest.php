<?php

namespace App\Features\Timesheets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrewMobileExternalInvoiceRequest extends FormRequest
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
        ];
    }
}
