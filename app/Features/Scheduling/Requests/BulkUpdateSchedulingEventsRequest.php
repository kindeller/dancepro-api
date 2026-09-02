<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateSchedulingEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['uuid', 'exists:scheduling_events,uuid'],
            'booking_item_ids' => ['nullable', 'array'],
            'booking_item_ids.*' => ['uuid', 'exists:concert_booking_items,uuid'],
            'action' => ['required', Rule::in(['approve', 'open', 'close', 'publish_roster'])],
            'deadline_date' => ['nullable', 'required_if:action,open', 'date_format:Y-m-d'],
        ];
    }
}
