<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeEventWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['draft', 'open', 'closed', 'publish_roster'])],
            'deadline' => [Rule::excludeIf($this->input('action') !== 'open'), 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
