<?php

namespace App\Features\Crew\Requests;

use App\Features\Crew\Support\RecognitionBadgeDesigns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRecognitionTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:20'],
            'design' => ['required', Rule::in(array_keys(RecognitionBadgeDesigns::options()))],
            'default_message' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
