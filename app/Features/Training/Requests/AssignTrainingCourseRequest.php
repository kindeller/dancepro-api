<?php

namespace App\Features\Training\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTrainingCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'crew_profile_ids' => ['present', 'array'],
            'crew_profile_ids.*' => ['integer', 'distinct', 'exists:crew_profiles,id'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
