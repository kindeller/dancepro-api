<?php

namespace App\Features\Scheduling\Requests;

use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEventTypeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        /** @var EventTypeDefinition|null $eventType */
        $eventType = $this->route('eventType');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('event_type_definitions', 'code')->ignore($eventType)],
            'system_category' => ['required', Rule::enum(SchedulingEventType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
