<?php

namespace App\Features\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveEventOperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'crew_brief' => ['nullable', 'string', 'max:50000'],
            'team_leader_notes' => ['nullable', 'string', 'max:50000'],
            'programme' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:102400'],
        ];
    }
}
