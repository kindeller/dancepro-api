<?php

namespace App\Features\CompetitionContacts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetitionContactStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return ['is_active' => ['present', 'boolean']];
    }
}
