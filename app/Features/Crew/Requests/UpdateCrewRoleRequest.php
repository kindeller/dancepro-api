<?php

namespace App\Features\Crew\Requests;

use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrewRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'event_type' => $this->filled('event_type_definition_id') ? EventTypeDefinition::query()->find($this->integer('event_type_definition_id'))?->system_category?->value : $this->input('event_type'),
        ]);
    }

    public function rules(): array
    {
        /** @var CrewRole $crewRole */
        $crewRole = $this->route('crewRole');

        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('crew_roles', 'code')->ignore($crewRole)],
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', Rule::enum(SchedulingEventType::class)],
            'event_type_definition_id' => ['nullable', 'integer', 'exists:event_type_definitions,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
