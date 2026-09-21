<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListStaffConcertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_uuid' => ['nullable', 'uuid', 'exists:studios,uuid'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
