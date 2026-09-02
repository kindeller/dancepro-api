<?php

namespace App\Features\Operations\Requests;

use App\Features\Scheduling\Models\EventTypeDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOperationalResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $definition = $this->filled('event_type_definition_id') ? EventTypeDefinition::query()->find($this->integer('event_type_definition_id')) : null;
        $this->merge(['is_active' => $this->boolean('is_active'), 'event_type' => $definition?->system_category?->value ?? $this->input('event_type')]);
    }

    public function rules(): array
    {
        return [
            'section_number' => ['nullable', 'integer', 'between:1,99'],
            'title' => ['required', 'string', 'max:255'],
            'resource_type' => ['required', Rule::in(['handbook', 'cheat_sheet', 'help'])],
            'event_type' => ['nullable', Rule::in(['competition', 'concert'])],
            'event_type_definition_id' => ['nullable', 'integer', 'exists:event_type_definitions,id'],
            'role_code' => ['nullable', 'string', 'max:80'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string', 'max:50000'],
            'external_url' => ['nullable', 'url', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:102400'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
