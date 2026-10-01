<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'requires_setup' => ['required', 'boolean'],
            'requires_set_down' => ['required', 'boolean'],
        ];
    }
}
