<?php

namespace App\Features\Operations\Requests;

use App\Features\Scheduling\Models\EventTypeDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveChecklistTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $items = collect(preg_split('/\R/', $this->string('items')->toString()))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
        $definition = $this->filled('event_type_definition_id') ? EventTypeDefinition::query()->find($this->integer('event_type_definition_id')) : null;
        $this->merge(['item_lines' => $items, 'is_active' => $this->boolean('is_active'), 'event_type' => $definition?->system_category?->value ?? $this->input('event_type')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', Rule::in(['competition', 'concert'])],
            'event_type_definition_id' => ['nullable', 'integer', 'exists:event_type_definitions,id'],
            'role_code' => ['nullable', 'string', 'max:80'],
            'item_lines' => ['required', 'array', 'min:1', 'max:50'],
            'item_lines.*' => ['required', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
