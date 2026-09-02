<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrewRoleMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'crew_profile_ids' => ['required', 'array'],
            'crew_profile_ids.*' => ['integer', 'distinct', 'exists:crew_profiles,id'],
            'crew_role_ids' => ['required', 'array'],
            'crew_role_ids.*' => ['integer', 'distinct', 'exists:crew_roles,id'],
            'assignments' => ['nullable', 'array'],
            'assignments.*' => ['array'],
            'assignments.*.*' => ['boolean'],
        ];
    }
}
