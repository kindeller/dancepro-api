<?php

namespace App\Features\Scheduling\Requests;

use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Features\Scheduling\Support\ShiftPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSchedulingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $days = collect($this->input('days', []))->filter(fn ($day) => filled($day['date'] ?? null))->values();
        $shifts = $days->flatMap(function (array $day): array {
            $shifts = [];
            foreach (ShiftPeriod::cases() as $period) {
                if (filter_var($day[$period->value] ?? false, FILTER_VALIDATE_BOOL)) {
                    $shifts[] = [
                        'uuid' => $day[$period->value.'_uuid'] ?? null,
                        'shift_date' => $day['date'],
                        'period' => $period->value,
                        'requires_setup' => ($day['setup_period'] ?? null) === $period->value,
                        'requires_set_down' => ($day['set_down_period'] ?? null) === $period->value,
                    ];
                }
            }

            return $shifts;
        })->values()->all();

        $this->merge([
            'days' => $days->all(),
            'shifts' => $shifts,
            'event_date' => $days->min('date'),
            'event_type_definition_id' => $this->input('event_type_definition_id') ?: EventTypeDefinition::query()->where('code', 'competition')->value('id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'competition_contact_id' => ['nullable', 'integer', 'exists:competition_contacts,id'],
            'organiser_name' => ['required', 'string', 'max:255'],
            'organiser_email' => ['required', 'email', 'max:255'],
            'organiser_phone' => ['required', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'event_type_definition_id' => ['required', 'integer', Rule::exists('event_type_definitions', 'id')->where('system_category', SchedulingEventType::Competition->value)->where('is_active', true)],
            'event_type' => ['required', Rule::in([SchedulingEventType::Competition->value])],
            'event_date' => ['required', 'date'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(['competition-videographer', 'competition-photographer-p1', 'competition-photographer-p2'])],
            'days' => ['required', 'array', 'min:1', 'max:31'],
            'days.*.date' => ['required', 'date', 'distinct'],
            'days.*.morning' => ['nullable', 'boolean'],
            'days.*.afternoon' => ['nullable', 'boolean'],
            'days.*.morning_uuid' => ['nullable', 'uuid'],
            'days.*.afternoon_uuid' => ['nullable', 'uuid'],
            'days.*.setup_period' => ['nullable', Rule::enum(ShiftPeriod::class)],
            'days.*.set_down_period' => ['nullable', Rule::enum(ShiftPeriod::class)],
            'shifts' => ['required', 'array', 'min:1'],
            'shifts.*.uuid' => ['nullable', 'uuid'],
            'shifts.*.shift_date' => ['required', 'date'],
            'shifts.*.period' => ['required', Rule::enum(ShiftPeriod::class)],
            'shifts.*.requires_setup' => ['nullable', 'boolean'],
            'shifts.*.requires_set_down' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('days', []) as $index => $day) {
                $morning = filter_var($day['morning'] ?? false, FILTER_VALIDATE_BOOL);
                $afternoon = filter_var($day['afternoon'] ?? false, FILTER_VALIDATE_BOOL);
                if (! $morning && ! $afternoon) {
                    $validator->errors()->add("days.{$index}.morning", 'Select morning, afternoon, or both.');
                }
                foreach (['setup_period', 'set_down_period'] as $field) {
                    if (filled($day[$field] ?? null) && ! filter_var($day[$day[$field]] ?? false, FILTER_VALIDATE_BOOL)) {
                        $validator->errors()->add("days.{$index}.{$field}", 'Setup and set-down must belong to a selected shift.');
                    }
                }
            }
        }];
    }
}
