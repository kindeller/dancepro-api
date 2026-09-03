<?php

namespace App\Features\Timesheets\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCrewMobileTimesheetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:2000'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['draft', 'ready_to_invoice', 'externally_invoiced', 'invoiced'])],
        ];
    }
}
