<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConcertTrackerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(['date', 'studio'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'phase' => ['nullable', Rule::in(['booking', 'preparation', 'editing', 'completion', 'all'])],
            'attention' => ['nullable', 'boolean'],
        ];
    }
}
