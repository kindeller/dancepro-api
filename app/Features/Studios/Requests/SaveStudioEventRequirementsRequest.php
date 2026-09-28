<?php

namespace App\Features\Studios\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveStudioEventRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'requirements' => ['sometimes', 'array'],
            'requirements.*' => ['in:portrait_space,portrait_dancers'],
        ];
    }
}
