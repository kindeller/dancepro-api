<?php

namespace App\Features\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCrewMobileDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return ['updated_since' => ['nullable', 'date']];
    }
}
