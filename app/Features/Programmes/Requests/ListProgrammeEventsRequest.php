<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListProgrammeEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->canAccessAdmin() || $this->user()->canAccessCrew());
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
