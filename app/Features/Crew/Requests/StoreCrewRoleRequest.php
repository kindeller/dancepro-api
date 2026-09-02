<?php

namespace App\Features\Crew\Requests;

use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrewRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('crew_roles', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', Rule::enum(SchedulingEventType::class)],
            'event_type_definition_id' => ['nullable', 'integer', 'exists:event_type_definitions,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('event_type_definition_id')) {
            $this->merge(['event_type' => EventTypeDefinition::query()->find($this->integer('event_type_definition_id'))?->system_category?->value]);
        }
    }
}
