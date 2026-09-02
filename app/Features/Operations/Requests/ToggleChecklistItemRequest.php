<?php

namespace App\Features\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['completed' => $this->boolean('completed')]);
    }

    public function rules(): array
    {
        return ['completed' => ['required', 'boolean']];
    }
}
