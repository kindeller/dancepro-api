<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCrewPayRateMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date'],
            'rates' => ['required', 'array'],
            'rates.*' => ['array'],
            'rates.*.*' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
