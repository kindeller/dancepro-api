<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDirectorCorrectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:0'], 'batch_uuid' => ['nullable', 'uuid'],
            'corrections_complete' => ['sometimes', 'boolean'], 'corrections_uploaded' => ['sometimes', 'boolean']];
    }
}
