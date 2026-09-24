<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConcertTrackerTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'waiting', 'complete', 'not_required'])],
            'assignee_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', true)],
            'due_date_override' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
