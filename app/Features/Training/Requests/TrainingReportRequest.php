<?php

namespace App\Features\Training\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['not_assigned', 'assigned', 'in_progress', 'overdue', 'completed'])],
            'course_id' => ['nullable', 'integer', 'exists:training_courses,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
