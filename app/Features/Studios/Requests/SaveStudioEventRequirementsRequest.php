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
            'group' => ['sometimes', 'required', 'in:portrait,event_details'],
            'requirements' => ['sometimes', 'array'],
            'requirements.*' => ['in:portrait_space,portrait_dancers,event_details'],
        ];
    }
}
