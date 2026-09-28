<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['studio' => ['nullable', 'uuid', 'exists:studios,uuid'], 'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'uuid'], 'status' => ['nullable', 'in:draft,open,closed']];
    }
}
