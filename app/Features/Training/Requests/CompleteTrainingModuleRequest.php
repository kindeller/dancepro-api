<?php

namespace App\Features\Training\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteTrainingModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return [
            'selected_option' => ['nullable', 'integer', 'min:0', 'max:99'],
            'answers' => ['nullable', 'array', 'max:100'],
            'answers.*' => ['nullable'],
        ];
    }
}
